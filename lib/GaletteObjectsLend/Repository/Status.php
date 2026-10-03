<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Repository;

use ArrayObject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Preferences;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Filters\StatusList;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;

/**
 * Lend status list
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractRepository<LendStatus>
 */
class Status extends AbstractRepository
{
    public const string TABLE = LendStatus::TABLE;
    public const string PK = LendStatus::PK;
    protected const string ALIAS = 'c';

    public const int DC_STOCK = 0;
    public const int IN_STOCK = 1;
    public const int OUT_STOCK = 2;

    public const int FILTER_NAME = 0;

    public const int ORDERBY_ID = 0;
    public const int ORDERBY_NAME = 1;
    public const int ORDERBY_ACTIVE = 2;
    public const int ORDERBY_STOCK = 3;
    public const int ORDERBY_RENTDAYS = 4;

    /** @var StatusList */
    protected \Galette\Core\Pagination $filters;

    /**
     * Default constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences instance
     * @param Login       $login       Logged in instance
     * @param ?StatusList $filters     Filtering
     */
    public function __construct(Db $zdb, Preferences $preferences, Login $login, ?StatusList $filters = null)
    {
        parent::__construct($zdb, $preferences, $login, 'LendStatus', $filters ?? new StatusList());
    }

    /**
     * Get status list
     *
     * @param bool $as_stt return the results as an array of Status object.
     * @param bool $count  true if we want to count rows
     * @param bool $limit  true if we want records pagination
     *
     * @return ($as_stt is true ? LendStatus[] : ResultSet)
     */
    public function getStatusList(bool $as_stt = false, bool $count = true, bool $limit = true): array|ResultSet
    {
        return $this->fetchList($as_stt, $count, $limit);
    }

    /**
     * Get whole status list
     *
     * @param bool $as_stt return the results as an array of Status object.
     *
     * @return ($as_stt is true ? LendStatus[] : ResultSet)
     */
    public function getList(bool $as_stt = false): array|ResultSet
    {
        return $this->fetchList($as_stt, false, false);
    }

    /**
     * Builds the SELECT statement
     */
    protected function buildSelect(): Select
    {
        $select = $this->zdb->select(LEND_PREFIX . self::TABLE, self::ALIAS);

        $this->whereActive($select, $this->filters->active_filter);

        if ($this->filters->stock_filter === self::IN_STOCK) {
            $select->where(['c.in_stock' => 1]);
        } elseif ($this->filters->stock_filter === self::OUT_STOCK) {
            $select->where(['c.in_stock' => 0]);
        }

        if ((string)$this->filters->filter_str !== '') {
            $select->where($this->contains('c.status_text', $this->filters->filter_str));
        }

        return $select;
    }

    /**
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    protected function buildOrderClause(): array
    {
        $field = match ($this->filters->orderby) {
            self::ORDERBY_ID => 'status_id',
            self::ORDERBY_NAME => 'status_text',
            self::ORDERBY_ACTIVE => 'is_active',
            self::ORDERBY_STOCK => 'in_stock',
            self::ORDERBY_RENTDAYS => 'rent_day_number',
            default => null
        };

        return $field === null ? [] : [$field . ' ' . $this->filters->getDirection()];
    }

    /**
     * Create an entity from a resultset row
     *
     * @param ArrayObject<string,mixed> $row Resultset row
     */
    protected function createEntity(ArrayObject $row): LendStatus
    {
        return new LendStatus($this->zdb, $row);
    }

    /**
     * Get active statuses of borrowed objects, sorted by text
     *
     * @return LendStatus[]
     */
    public function getActiveTakeAwayStatuses(): array
    {
        return $this->getActiveStatuses(false);
    }

    /**
     * Get active in stock statuses, sorted by text
     *
     * @return LendStatus[]
     */
    public function getActiveStockStatuses(): array
    {
        return $this->getActiveStatuses(true);
    }

    /**
     * Get active statuses, sorted by text
     *
     * @param bool $in_stock In stock or borrowed statuses
     *
     * @return LendStatus[]
     */
    private function getActiveStatuses(bool $in_stock): array
    {
        $select = $this->zdb->select(LEND_PREFIX . self::TABLE)
            ->where(['is_active' => 1, 'in_stock' => (int)$in_stock])
            ->order('status_text');

        $statuses = [];
        foreach ($this->zdb->execute($select) as $row) {
            $statuses[] = new LendStatus($this->zdb, $row);
        }
        return $statuses;
    }
}
