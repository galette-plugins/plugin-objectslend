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
use Galette\Core\Pagination;
use Galette\Core\Preferences;
use Galette\Repository\Repository;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;

/**
 * Common code for lists of objects, categories and statuses
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TEntity of object
 */
abstract class AbstractRepository extends Repository
{
    public const int ALL = 0;
    public const int ACTIVE = 1;
    public const int INACTIVE = 2;

    public const string PK = '';
    /** Table alias used in queries */
    protected const string ALIAS = '';

    private ?int $count = null;

    /**
     * Constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Preferences instance
     * @param Login       $login       Logged in instance
     * @param string      $entity      Entity class name, relative to the Entity namespace
     * @param Pagination  $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Preferences $preferences,
        Login $login,
        string $entity,
        Pagination $filters
    ) {
        parent::__construct($zdb, $preferences, $login, 'Entity\\' . $entity, 'GaletteObjectsLend', LEND_PREFIX);
        $this->filters = $filters;
    }

    /**
     * Builds the SELECT statement, filtered but neither ordered nor limited
     */
    abstract protected function buildSelect(): Select;

    /**
     * Builds the order clause
     *
     * @return array<string>
     */
    abstract protected function buildOrderClause(): array;

    /**
     * Create an entity from a resultset row
     *
     * @param ArrayObject<string,mixed> $row Resultset row
     *
     * @return TEntity
     */
    abstract protected function createEntity(ArrayObject $row): object;

    /**
     * Get the list
     *
     * @param bool $as_entities Return entities instead of a resultset
     * @param bool $count       Count all matching rows, for pagination
     * @param bool $limit       Only retrieve the rows of the current page
     *
     * @return ($as_entities is true ? array<int, TEntity> : ResultSet)
     */
    protected function fetchList(bool $as_entities, bool $count, bool $limit): array|ResultSet
    {
        $select = $this->buildSelect();
        $select->order($this->buildOrderClause());

        if ($count) {
            $this->proceedCount($select);
        }
        if ($limit) {
            $this->filters->setLimits($select);
        }

        /** @var ResultSet $rows */
        $rows = $this->zdb->execute($select);

        if (!$as_entities) {
            return $rows;
        }

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->createEntity($row);
        }
        return $list;
    }

    /**
     * Count rows matching the query
     *
     * Counting on a subquery keeps grouping and HAVING clauses right.
     *
     * @param Select $select Original select
     */
    private function proceedCount(Select $select): void
    {
        $counted = clone $select;
        $counted->reset(Select::COLUMNS);
        $counted->reset(Select::ORDER);
        $counted->reset(Select::JOINS);
        $counted->columns(['id' => new Expression(static::ALIAS . '.' . static::PK)]);
        foreach ($select->joins as $join) {
            $counted->join($join['name'], $join['on'], [], $join['type']);
        }

        $count_select = new Select(['counted' => $counted]);
        $count_select->columns(['count' => new Expression('COUNT(*)')]);

        $result = $this->zdb->execute($count_select)->current();
        $this->count = (int)$result['count'];
        $this->filters->setCounter($this->count);
    }

    /**
     * Get count for current query
     */
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * Case-insensitive search on a column
     *
     * @param string $column Qualified column name, as a trusted literal
     * @param string $search Searched string
     */
    protected function contains(string $column, string $search): PredicateExpression
    {
        return new PredicateExpression(
            'LOWER(' . $column . ') LIKE ?',
            ['%' . mb_strtolower($search) . '%']
        );
    }

    /**
     * Filter on activity
     *
     * @param Select $select Select
     * @param int    $filter One of ALL, ACTIVE or INACTIVE
     */
    protected function whereActive(Select $select, ?int $filter): void
    {
        if ($filter === self::ACTIVE) {
            $select->where([static::ALIAS . '.is_active' => 1]);
        } elseif ($filter === self::INACTIVE) {
            $select->where([static::ALIAS . '.is_active' => 0]);
        }
    }

    /**
     * Nothing to initialize
     *
     * @param bool $check_first Check first if it seems initialized
     */
    public function installInit(bool $check_first = true): bool
    {
        return true;
    }
}
