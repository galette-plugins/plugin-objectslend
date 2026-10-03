<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend;

use Galette\Core\Db;
use Galette\Entity\Adherent;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use Throwable;

/**
 * Sample data: categories, statuses, objects and their rents history
 *
 * Data live in `scripts/sample-data.json`. Dates there are days relative to
 * the import, so a history loaded today reads as recent. The last rent of each
 * object is its current one, it is never closed.
 *
 * Categories and statuses are looked up by name and created if missing. Rents
 * are given to existing members, picked by their position in the list passed
 * in; with no members, rents are anonymous.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @phpstan-type Rent array{status: string, member?: int, begin: int, end?: int, comment?: string}
 * @phpstan-type Item array{
 *     name: string,
 *     description: string,
 *     serial: string,
 *     price: float|int,
 *     rent_price: float|int,
 *     per_day: bool,
 *     dimension: string,
 *     weight: float|int,
 *     category: ?string,
 *     active: bool,
 *     rents: list<Rent>
 * }
 */
final class SampleData
{
    public const string FILE = __DIR__ . '/../../scripts/sample-data.json';

    /** @var array<string, int> Category key => id */
    private array $categories = [];
    /** @var array<string, int> Status key => id */
    private array $statuses = [];

    /**
     * Constructor
     *
     * @param Db     $zdb  Database instance
     * @param string $file Data file
     */
    public function __construct(private readonly Db $zdb, private readonly string $file = self::FILE)
    {
    }

    /**
     * Does the catalog already hold objects?
     *
     * Sample data are meant for an empty catalog; loading them twice would
     * duplicate every object.
     */
    public function hasObjects(): bool
    {
        $select = $this->zdb->select(LEND_PREFIX . LendObject::TABLE);
        $select->limit(1);
        return $this->zdb->execute($select)->count() > 0;
    }

    /**
     * Get active members ids, as sample data would pick them
     *
     * @param int $limit Maximum number of members
     *
     * @return list<int>
     */
    public function getMembers(int $limit = 10): array
    {
        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns([Adherent::PK])
            ->where(['activite_adh' => true])
            ->order(Adherent::PK)
            ->limit($limit);

        $ids = [];
        foreach ($this->zdb->execute($select) as $row) {
            $ids[] = (int)$row->{Adherent::PK};
        }
        return $ids;
    }

    /**
     * Load sample data, in a single transaction
     *
     * @param list<int> $members Members to give rents to
     *
     * @return array{categories: int, statuses: int, objects: int, rents: int} What was created
     */
    public function load(array $members): array
    {
        $data = $this->read();
        $created = ['categories' => 0, 'statuses' => 0, 'objects' => 0, 'rents' => 0];

        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }

            foreach ($data['categories'] as $category) {
                $created['categories'] += (int)$this->addCategory($category);
            }
            foreach ($data['statuses'] as $status) {
                $created['statuses'] += (int)$this->addStatus($status);
            }
            foreach ($data['objects'] as $item) {
                $created['rents'] += $this->addObject($item, $members);
                ++$created['objects'];
            }

            if ($need_transaction) {
                $this->zdb->commit();
            }
        } catch (Throwable $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }
            throw $e;
        }

        return $created;
    }

    /**
     * Read and check data file
     *
     * @return array{
     *     categories: list<array{key: string, name: string, active: bool}>,
     *     statuses: list<array{key: string, text: string, in_stock: bool, active: bool, days?: int}>,
     *     objects: list<Item>
     * }
     */
    private function read(): array
    {
        $contents = file_get_contents($this->file);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read sample data file %s', $this->file));
        }
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['categories'], $data['statuses'], $data['objects'])) {
            throw new \RuntimeException(sprintf('Invalid sample data file %s', $this->file));
        }
        return $data;
    }

    /**
     * Get category, create it if missing
     *
     * @param array{key: string, name: string, active: bool} $data Category data
     *
     * @return bool Whether it has been created
     */
    private function addCategory(array $data): bool
    {
        $select = $this->zdb->select(LEND_PREFIX . LendCategory::TABLE)
            ->where(['name' => $data['name']])
            ->limit(1);
        $row = $this->zdb->execute($select)->current();
        if ($row) {
            $this->categories[$data['key']] = (int)$row->{LendCategory::PK};
            return false;
        }

        $category = new LendCategory($this->zdb);
        $category->setName($data['name'])->setActive($data['active']);
        $category->store();
        $this->categories[$data['key']] = (int)$category->getId();
        return true;
    }

    /**
     * Get status, create it if missing
     *
     * @param array{key: string, text: string, in_stock: bool, active: bool, days?: int} $data Status data
     *
     * @return bool Whether it has been created
     */
    private function addStatus(array $data): bool
    {
        $select = $this->zdb->select(LEND_PREFIX . LendStatus::TABLE)
            ->where(['status_text' => $data['text']])
            ->limit(1);
        $row = $this->zdb->execute($select)->current();
        if ($row) {
            $this->statuses[$data['key']] = (int)$row->{LendStatus::PK};
            return false;
        }

        $status = new LendStatus($this->zdb);
        $status
            ->setText($data['text'])
            ->setInStock($data['in_stock'])
            ->setActive($data['active'])
            ->setRentDayNumber($data['days'] ?? null);
        $status->store();
        $this->statuses[$data['key']] = (int)$status->getId();
        return true;
    }

    /**
     * Create object, its rents, and set the last one as current
     *
     * @param Item      $data    Object data
     * @param list<int> $members Members to give rents to
     *
     * @return int Number of rents created
     */
    private function addObject(array $data, array $members): int
    {
        $object = new LendObject($this->zdb);
        $object
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setSerialNumber($data['serial'])
            ->setPrice((float)$data['price'])
            ->setRentPrice((float)$data['rent_price'])
            ->setPricePerDay($data['per_day'])
            ->setDimension($data['dimension'])
            ->setWeight((float)$data['weight'])
            ->setActive($data['active'])
            ->setCategoryId($data['category'] !== null ? $this->categories[$data['category']] : null);
        $object->store();
        $object_id = (int)$object->getId();

        $rent = null;
        $last = count($data['rents']) - 1;
        foreach ($data['rents'] as $i => $entry) {
            $rent = new LendRent($this->zdb);
            $rent
                ->setObjectId($object_id)
                ->setStatusId($this->statuses[$entry['status']])
                ->setAdherentId($this->getMember($entry['member'] ?? null, $members))
                ->setComments($entry['comment'] ?? '')
                ->setDateBegin($this->getDate($entry['begin']));
            //the last rent is the current one
            if ($i < $last && isset($entry['end'])) {
                $rent->setDateEnd($this->getDate($entry['end']));
            }
            $rent->store();
        }

        if ($rent !== null) {
            $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE)
                ->set([LendRent::PK => $rent->getId()])
                ->where([LendObject::PK => $object_id]);
            $this->zdb->execute($update);
        }

        return count($data['rents']);
    }

    /**
     * Get member for a rent
     *
     * @param ?int      $position Member position in sample data
     * @param list<int> $members  Available members
     */
    private function getMember(?int $position, array $members): ?int
    {
        if ($position === null || count($members) === 0) {
            return null;
        }
        return $members[$position % count($members)];
    }

    /**
     * Get date from a number of days relative to today
     *
     * @param int $days Days, negative in the past
     */
    private function getDate(int $days): string
    {
        return (new \DateTimeImmutable())->modify(sprintf('%+d days', $days))->format('Y-m-d H:i:s');
    }
}
