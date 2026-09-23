<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use GaletteObjectsLend\Filters\StatusList;
use GaletteObjectsLend\Entity\LendStatus;
use Galette\Core\Login;
use Laminas\Db\Sql\Select;

/**
 * Status list
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Status
{
    public const string TABLE = LendStatus::TABLE;
    public const string PK = LendStatus::PK;

    public const int ALL = 0;
    public const int ACTIVE = 1;
    public const int INACTIVE = 2;

    public const int DC_STOCK = 0;
    public const int IN_STOCK = 1;
    public const int OUT_STOCK = 2;

    public const int FILTER_NAME = 0;

    public const int ORDERBY_ID = 0;
    public const int ORDERBY_NAME = 1;
    public const int ORDERBY_ACTIVE = 2;
    public const int ORDERBY_STOCK = 3;
    public const int ORDERBY_RENTDAYS = 4;

    private Db $zdb;
    private Login $login;

    private StatusList $filters;
    private ?int $count = null;
    /** @var array<string> */
    private array $errors = [];

    /**
     * Default constructor
     *
     * @param Db          $zdb     Database instance
     * @param Login       $login   Logged in instance
     * @param ?StatusList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?StatusList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new StatusList();
        } else {
            $this->filters = $filters;
        }
    }


    /**
     * Get status list
     *
     * @param bool      $as_stt return the results as an array of
     *                          Status object.
     * @param ?string[] $fields field(s) name(s) to get. If null, all fields will be returned
     * @param bool      $count  true if we want to count members
     * @param bool      $limit  true if we want records pagination
     *
     * @return LendStatus[]|ResultSet
     */
    public function getStatusList(
        bool $as_stt = false,
        ?array $fields = null,
        bool $count = true,
        bool $limit = true
    ): array|ResultSet {
        try {
            $select = $this->buildSelect($fields, false, $count);

            //add limits to retrieve only relevant rows
            if ($limit === true) {
                $this->filters->setLimits($select);
            }

            $rows = $this->zdb->execute($select);
            $this->filters->query = $this->zdb->query_string;

            $status = [];
            if ($as_stt) {
                foreach ($rows as $row) {
                    $status[] = new LendStatus($this->zdb, $row);
                }
            } else {
                $status = $rows;
            }
            return $status;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list categories | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get status list
     *
     * @param bool      $as_stt return the results as an array of
     *                          Status object.
     * @param ?string[] $fields field(s) name(s) to get. Should be a string or
     *                          an array. If null, all fields will be
     *                          returned
     *
     * @return LendStatus[]|ResultSet
     */
    public function getList(bool $as_stt = false, ?array $fields = null): array|ResultSet
    {
        return $this->getStatusList(
            $as_stt,
            $fields,
            false,
            false
        );
    }

    /**
     * Builds the SELECT statement
     *
     * @param string[] $fields fields list to retrieve
     * @param bool     $photos true if we want to get only members with photos
     *                         Default to false, only relevant for SHOW_PUBLIC_LIST
     * @param bool     $count  true if we want to count members, defaults to false
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?array $fields, bool $photos = false, bool $count = false): Select
    {
        try {
            $fieldsList = ($fields != null)
                            ? ((!is_array($fields) || count($fields) < 1) ? (array)'*'
                            : $fields) : (array)'*';

            $select = $this->zdb->select(LEND_PREFIX . self::TABLE, 'c');
            $select->columns($fieldsList);

            if ($this->filters !== false) {
                $this->buildWhereClause($select);
            }
            $select->order($this->buildOrderClause($fields));

            if ($count) {
                $this->proceedCount($select);
            }

            return $select;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot build SELECT clause for objectslend status | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count members from the query
     *
     * @param Select $select Original select
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::HAVING);
            $countSelect->columns(
                [
                    'count' => new Expression('count(c.' . self::PK . ')')
                ]
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

            $results = $this->zdb->execute($countSelect);

            $this->count = (int)$results->current()->count;
            if ($this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count objectslend status | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @param ?string[] $fields Fields list to ensure ORDER clause
     *                          references selected fields. Optional.
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(?array $fields = null): array
    {
        $order = [];
        switch ($this->filters->orderby) {
            case self::ORDERBY_ID:
                if ($this->canOrderBy('status_id', $fields)) {
                    $order[] = 'status_id ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_NAME:
                if ($this->canOrderBy('status_text', $fields)) {
                    $order[] = 'status_text ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_ACTIVE:
                if ($this->canOrderBy('is_active', $fields)) {
                    $order[] = 'is_active ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_STOCK:
                if ($this->canOrderBy('in_stock', $fields)) {
                    $order[] = 'in_stock ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_RENTDAYS:
                if ($this->canOrderBy('rent_day_number', $fields)) {
                    $order[] = 'rent_day_number ' . $this->filters->getDirection();
                }
                break;
        }

        return $order;
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            if ($this->filters->active_filter == self::ACTIVE) {
                $select->where('c.is_active = true');
            }
            if ($this->filters->active_filter == self::INACTIVE) {
                $select->where('c.is_active = false');
            }

            if ($this->filters->stock_filter == self::IN_STOCK) {
                $select->where('c.in_stock = true');
            }
            if ($this->filters->stock_filter == self::OUT_STOCK) {
                $select->where('c.in_stock = false');
            }

            if ($this->filters->filter_str != '') {
                $token = $this->zdb->platform->quoteValue(
                    '%' . strtolower($this->filters->filter_str) . '%'
                );

                $select->where(
                    'LOWER(c.status_text) LIKE ' . $token
                );
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
        }
    }

    /**
     * Is field allowed to order? it should be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string    $field_name Field name to order by
     * @param ?string[] $fields     SELECTE'ed fields
     */
    private function canOrderBy(string $field_name, ?array $fields): bool
    {
        if (!is_array($fields)) {
            return true;
        } elseif (in_array($field_name, $fields)) {
            return true;
        } else {
            Analog::log(
                'Trying to order by ' . $field_name . ' while it is not in '
                . 'selected fields.',
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Get count for current query
     */
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * Get registered errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
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
