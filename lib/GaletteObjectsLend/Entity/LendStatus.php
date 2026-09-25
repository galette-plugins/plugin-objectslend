<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

use ArrayObject;
use Galette\Core\Db;

/**
 * Lend status management
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendStatus
{
    public const string TABLE = 'status';
    public const string PK = 'status_id';

    private Db $zdb;

    /** @var array<string,string> */
    private array $fields = [
        'status_id' => 'integer',
        'status_text' => 'varchar(100)',
        'in_stock' => 'boolean',
        'is_active' => 'boolean',
        'rent_day_number' => 'int'
    ];
    private ?int $status_id = null;
    private string $status_text = '';
    private bool $in_stock = false;
    private bool $is_active = true;
    private ?int $rent_day_number = null;

    /**
     * Status constructor
     *
     * @param Db                                 $zdb  Database instance
     * @param int|ArrayObject<string,mixed>|null $args Can be null, an ID or a database row
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;

        if (is_int($args)) {
            $select = $this->zdb->select(LEND_PREFIX . self::TABLE)
                    ->where([self::PK => $args]);
            $result = $this->zdb->execute($select);
            if ($result->count() == 1) {
                $this->loadFromRS($result->current());
            }
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string,mixed> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->status_id = (int)$r['status_id'];
        $this->status_text = (string)$r['status_text'];
        $this->in_stock = $r['in_stock'] == '1';
        $this->is_active = $r['is_active'] == '1';
        $this->rent_day_number = $r['rent_day_number'] != null ? (int)$r['rent_day_number'] : null;
    }

    /**
     * Store current element
     */
    public function store(): void
    {
        $values = [];

        foreach (array_keys($this->fields) as $k) {
            if (
                ($k === 'is_active' || $k === 'in_stock')
                && $this->$k === false
            ) {
                //Handle booleans for postgres ; bugs #18899 and #19354
                $values[$k] = $this->zdb->isPostgres() ? 'false' : 0;
            } else {
                $values[$k] = $this->$k ?? null;
            }
        }

        if ($this->status_id === null) {
            unset($values[self::PK]);
            $insert = $this->zdb->insert(LEND_PREFIX . self::TABLE)
                    ->values($values);
            $result = $this->zdb->execute($insert);
            if ($result->count() > 0) {
                if ($this->zdb->isPostgres()) {
                    /** @phpstan-ignore-next-line */
                    $this->status_id = (int)$this->zdb->driver->getLastGeneratedValue(
                        PREFIX_DB . 'lend_status_id_seq'
                    );
                } else {
                    $this->status_id = (int)$this->zdb->driver->getLastGeneratedValue();
                }
            } else {
                throw new \Exception(_T("Status has not been added :(", "objectslend"));
            }
        } else {
            $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                    ->set($values)
                    ->where([self::PK => $this->status_id]);
            $this->zdb->execute($update);
        }
    }

    /**
     * Is status used by a rent, current or past?
     *
     * Rents keep their status for the history: such a status cannot be removed.
     */
    public function isUsed(): bool
    {
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE)
            ->columns([LendRent::PK])
            ->where([self::PK => $this->status_id])
            ->limit(1);
        return $this->zdb->execute($select)->count() > 0;
    }

    /**
     * Delete status
     */
    public function delete(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . self::TABLE)
                ->where([self::PK => $this->status_id]);
        $this->zdb->execute($delete);
    }

    /**
     * Get ID
     */
    public function getId(): ?int
    {
        return $this->status_id;
    }

    /**
     * Get text
     */
    public function getText(): string
    {
        return $this->status_text;
    }

    /**
     * Set text
     *
     * @param string $text Status text
     */
    public function setText(string $text): self
    {
        $this->status_text = $text;
        return $this;
    }

    /**
     * Is object in stock with this status?
     */
    public function isInStock(): bool
    {
        return $this->in_stock;
    }

    /**
     * Set in stock
     *
     * @param bool $in_stock In stock
     */
    public function setInStock(bool $in_stock): self
    {
        $this->in_stock = $in_stock;
        return $this;
    }

    /**
     * Is status active?
     */
    public function isActive(): bool
    {
        return $this->is_active;
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
     * Get number of days of rent
     */
    public function getRentDayNumber(): ?int
    {
        return $this->rent_day_number;
    }

    /**
     * Set number of days of rent
     *
     * @param ?int $days Number of days, null for none
     */
    public function setRentDayNumber(?int $days): self
    {
        $this->rent_day_number = $days;
        return $this;
    }
}
