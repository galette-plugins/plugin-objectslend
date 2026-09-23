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
use Laminas\Db\Sql\Predicate\Operator;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Filters\ObjectsList;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use Galette\Core\Login;
use Laminas\Db\Sql\Select;

/**
 * Categories list
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Categories
{
    public const string TABLE = LendCategory::TABLE;
    public const string PK = LendCategory::PK;

    public const int ALL_CATEGORIES = 0;
    public const int ACTIVE_CATEGORIES = 1;
    public const int INACTIVE_CATEGORIES = 2;

    public const int FILTER_NAME = 0;

    public const int ORDERBY_NAME = 0;
    public const int ORDERBY_ACTIVITY = 1;

    private CategoriesList $filters;
    private ?int $count = null;
    /** @var array<string> */
    private array $errors = [];

    private Db $zdb;
    private Login $login;

    /**
     * Default constructor
     *
     * @param Db              $zdb     Database instance
     * @param Login           $login   Logged in instance
     * @param ?CategoriesList $filters Filtering
     */
    public function __construct(Db $zdb, Login $login, ?CategoriesList $filters = null)
    {
        $this->zdb = $zdb;
        $this->login = $login;

        if ($filters === null) {
            $this->filters = new CategoriesList();
        } else {
            $this->filters = $filters;
        }
    }


    /**
     * Get categories list
     *
     * @param bool           $as_cat return the results as an array of
     *                               Categories object.
     * @param ?array<string> $fields field(s) name(s) to get. Should be a string or
     *                               an array. If null, all fields will be
     *                               returned
     * @param bool           $count  true if we want to count members
     * @param bool           $limit  true if we want records pagination
     *
     * @return LendCategory[]|ResultSet
     */
    public function getCategoriesList(
        bool $as_cat = false,
        ?array $fields = null,
        bool $count = true,
        bool $limit = true
    ): array|ResultSet {
        try {
            $select = $this->buildSelect($fields, $count);

            //add limits to retrieve only relevant rows
            if ($limit === true) {
                $this->filters->setLimits($select);
            }

            $rows = $this->zdb->execute($select);
            $this->filters->query = $this->zdb->query_string;

            $categories = [];
            if ($as_cat) {
                foreach ($rows as $row) {
                    $categories[] = new LendCategory($this->zdb, $row);
                }
            } else {
                $categories = $rows;
            }
            return $categories;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list categories | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get Categories list
     *
     * @param bool   $as_cat return the results as an array of
     *                       Category object.
     * @param ?array $fields field(s) name(s) to get. If null, all fields will be returned
     *
     * @return LendCategory[]|ResultSet
     */
    public function getList(bool $as_cat = false, ?array $fields = null): array|ResultSet
    {
        return $this->getCategoriesList(
            $as_cat,
            $fields,
            false,
            false
        );
    }

    /**
     * Builds the SELECT statement
     *
     * @param ?array<string> $fields fields list to retrieve
     * @param bool           $count  true if we want to count members, defaults to false
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?array $fields, bool $count = false): Select
    {
        try {
            $fieldsList = [
                '*',
                'objects_count'     => new Expression('COUNT(o.' . self::PK . ')'),
                'objects_price_sum' => new Expression('SUM(o.price)')
            ];

            if ($fields !== null && is_array($fields)) {
                array_merge($fieldsList, $fields);
            }

            $select = $this->zdb->select(LEND_PREFIX . self::TABLE, 'c');
            $select->columns($fieldsList);

            $select->join(
                ['o' => PREFIX_DB . LEND_PREFIX . LendObject::TABLE],
                'o.' . LendCategory::PK . '=c.' . LendCategory::PK,
                [],
                $select::JOIN_LEFT
            );

            if ($this->filters !== false) {
                $this->buildWhereClause($select);
            }
            $select->order($this->buildOrderClause($fields));

            if ($count) {
                $this->proceedCount($select);
            }

            $select->group(
                'c.category_id'
            );

            return $select;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot build SELECT clause for categories | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Count categories from the query
     *
     * @param Select $select Original select
     */
    private function proceedCount(Select $select): void
    {
        try {
            $countSelect = clone $select;
            $countSelect->reset($countSelect::COLUMNS);
            $countSelect->reset($countSelect::ORDER);
            $countSelect->reset($countSelect::JOINS);
            $countSelect->columns(
                [
                    'count' => new Expression('count(DISTINCT c.' . self::PK . ')')
                ]
            );

            $joins = $select->getRawState($select::JOINS);
            foreach ($joins as $join) {
                $countSelect->join(
                    $join['name'],
                    $join['on'],
                    [],
                    $join['type']
                );
            }

            $results = $this->zdb->execute($countSelect);
            $this->count = 0;
            if ($results->count() > 0) {
                $this->count = (int)$results->current()->count;
            }
            $this->filters->setCounter($this->count);
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count categories | ' . $e->getMessage(),
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
            case self::ORDERBY_NAME:
                if ($this->canOrderBy('name', $fields)) {
                    $order[] = 'name ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_ACTIVITY:
                if ($this->canOrderBy('is_active', $fields)) {
                    $order[] = 'is_active ' . $this->filters->getDirection();
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
            //if there are filters on objects; add them
            if ($this->filters->objects_filters instanceof ObjectsList) {
                $objects = new Objects(
                    $this->zdb,
                    new \GaletteObjectsLend\Entity\Preferences($this->zdb),
                    $this->filters->objects_filters
                );
                $objects->buildWhereClause($select);
            }

            if ($this->filters->active_filter == self::ACTIVE_CATEGORIES) {
                $select->where('c.is_active = true');
            }
            if ($this->filters->active_filter == self::INACTIVE_CATEGORIES) {
                $select->where('c.is_active = false');
            }

            if ($this->filters->filter_str != '') {
                $token = $this->zdb->platform->quoteValue(
                    '%' . strtolower($this->filters->filter_str) . '%'
                );

                $select->where(
                    'LOWER(c.name) LIKE ' . $token
                );
            }

            if ($this->filters->not_empty == true) {
                $select->having(
                    new Operator(
                        new Expression('COUNT(o.' . self::PK . ')'),
                        '>',
                        '0'
                    )
                );
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Is field allowed to order? it should be present in
     * provided fields list (those that are SELECT'ed).
     *
     * @param string         $field_name Field name to order by
     * @param ?array<string> $fields     SELECTE'ed fields
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
}
