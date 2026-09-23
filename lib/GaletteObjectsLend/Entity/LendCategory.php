<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

use Analog\Analog;
use ArrayObject;
use Galette\Core\Db;
use Laminas\Db\Sql\Predicate;

/**
 * Categories management
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendCategory
{
    public const string TABLE = 'category';
    public const string PK = 'category_id';

    /** @var array<string> */
    private array $fields = [
        'category_id' => 'integer',
        'name' => 'varchar(100)',
        'is_active' => 'boolean'
    ];
    private ?int $category_id = null;
    private ?string $name = null;
    private bool $is_active = true;
    private int $objects_nb = 0;
    private float $objects_price_sum = 0.0;
    private CategoryPicture $picture;

    /** @var array<string, bool> */
    private array $deps = [
        'picture'   => true
    ];

    private Db $zdb;

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param int|ArrayObject<string,int|string>|null $args Maybe null, an RS object or an id from database
     * @param ?array<string,bool>                     $deps Dependencies configuration, see LendCategory::$deps
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null, ?array $deps = null)
    {
        $this->zdb = $zdb;

        if ($deps !== null) {
            $this->deps = array_merge(
                $this->deps,
                $deps
            );
        }

        if ($this->deps['picture'] === true) {
            $this->picture = new CategoryPicture();
        }

        if (is_int($args)) {
            try {
                $select = $this->zdb->select(LEND_PREFIX . self::TABLE)
                        ->where([self::PK => $args]);
                $results = $this->zdb->execute($select);
                if ($results->count() == 1) {
                    $this->loadFromRS($results->current());
                }
            } catch (\Exception $e) {
                Analog::log(
                    'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                    . $e->getTraceAsString(),
                    Analog::ERROR
                );
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->category_id = (int)$r->category_id;
        $this->name = $r->name;
        $this->is_active = $r->is_active == '1';

        if (property_exists($r, 'objects_count')) {
            $this->objects_nb = (int)$r->objects_count;
        }

        if (property_exists($r, 'objects_price_sum') && $r->objects_price_sum !== null) {
            $this->objects_price_sum = (float)$r->objects_price_sum;
        }

        if ($this->deps['picture'] === true) {
            $this->picture = new CategoryPicture((int)$this->category_id);
        }
    }

    /**
     * Store category
     */
    public function store(): bool
    {
        try {
            $values = [];

            foreach (array_keys($this->fields) as $k) {
                if ($k === 'is_active' && $this->$k === false) {
                    //Handle booleans for postgres ; bugs #18899 and #19354
                    $values[$k] = $this->zdb->isPostgres() ? 'false' : 0;
                } else {
                    $values[$k] = $this->$k ?? null;
                }
            }

            if ($this->category_id === null) {
                unset($values['category_id']);
                $insert = $this->zdb->insert(LEND_PREFIX . self::TABLE)
                        ->values($values);
                $result = $this->zdb->execute($insert);
                if ($result->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->category_id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . 'lend_category_id_seq'
                        );
                    } else {
                        $this->category_id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }
                } else {
                    throw new \RuntimeException('Unable to add category!');
                }
            } else {
                $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                        ->set($values)
                        ->where([self::PK => $this->category_id]);
                $this->zdb->execute($update);
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Drop a category. All objects for removed category will be assigned to none.
     */
    public function delete(): bool
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }
            $select = $this->zdb->select(LEND_PREFIX . LendObject::TABLE)
                    ->where(['category_id' => $this->category_id]);
            $results = $this->zdb->execute($select);
            if ($results->count() > 0) {
                $values = ['category_id' => new Predicate\Expression('NULL')];
                $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE)
                        ->set($values)
                        ->where(['category_id' => $this->category_id]);
                $this->zdb->execute($update);
            }

            $delete = $this->zdb->delete(LEND_PREFIX . self::TABLE)
                    ->where([self::PK => $this->category_id]);
            $this->zdb->execute($delete);
            if ($need_transaction) {
                $this->zdb->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Get category name
     *
     * @param bool $count Whether to display count along with name (defaults to true)
     */
    public function getName(bool $count = true): string
    {
        $name = $this->name !== null ? $this->name : _T("No category", "objectslend");

        if ($count === true) {
            $name .= " ({$this->objects_nb})";
        }

        return $name;
    }

    /**
     * Set name
     *
     * @param string $name Category name
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set active
     *
     * @param bool $active Active
     */
    public function setActive(bool $active): self
    {
        $this->is_active = $active;
        return $this;
    }

    /**
     * Get category ID
     */
    public function getId(): ?int
    {
        return $this->category_id;
    }

    /**
     * Is object active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get picture
     */
    public function getPicture(): ?CategoryPicture
    {
        return $this->picture ?? null;
    }

    /**
     * Get sum
     */
    public function getSum(): float
    {
        return $this->objects_price_sum;
    }

    /**
     * Get objects count
     */
    public function getObjectsNb(): int
    {
        return $this->objects_nb;
    }
}
