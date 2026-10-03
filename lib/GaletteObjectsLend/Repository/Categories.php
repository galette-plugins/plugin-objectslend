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
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Filters\ObjectsList;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;

/**
 * Categories list
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractRepository<LendCategory>
 */
class Categories extends AbstractRepository
{
    public const string TABLE = LendCategory::TABLE;
    public const string PK = LendCategory::PK;
    protected const string ALIAS = 'c';

    public const int ALL_CATEGORIES = self::ALL;
    public const int ACTIVE_CATEGORIES = self::ACTIVE;
    public const int INACTIVE_CATEGORIES = self::INACTIVE;

    public const int FILTER_NAME = 0;

    public const int ORDERBY_NAME = 0;
    public const int ORDERBY_ACTIVITY = 1;

    /** @var CategoriesList */
    protected \Galette\Core\Pagination $filters;

    /**
     * Default constructor
     *
     * @param Db              $zdb         Database instance
     * @param Preferences     $preferences Preferences instance
     * @param Login           $login       Logged in instance
     * @param ?CategoriesList $filters     Filtering
     */
    public function __construct(Db $zdb, Preferences $preferences, Login $login, ?CategoriesList $filters = null)
    {
        parent::__construct($zdb, $preferences, $login, 'LendCategory', $filters ?? new CategoriesList());
    }

    /**
     * Get categories list
     *
     * @param bool $as_cat return the results as an array of Category object.
     * @param bool $count  true if we want to count rows
     * @param bool $limit  true if we want records pagination
     *
     * @return ($as_cat is true ? LendCategory[] : ResultSet)
     */
    public function getCategoriesList(bool $as_cat = false, bool $count = true, bool $limit = true): array|ResultSet
    {
        return $this->fetchList($as_cat, $count, $limit);
    }

    /**
     * Get whole categories list
     *
     * @param bool $as_cat return the results as an array of Category object.
     *
     * @return ($as_cat is true ? LendCategory[] : ResultSet)
     */
    public function getList(bool $as_cat = false): array|ResultSet
    {
        return $this->fetchList($as_cat, false, false);
    }

    /**
     * Builds the SELECT statement
     */
    protected function buildSelect(): Select
    {
        $select = $this->zdb->select(LEND_PREFIX . self::TABLE, self::ALIAS);
        $select->columns([
            '*',
            'objects_count'     => new Expression('COUNT(o.' . LendObject::PK . ')'),
            'objects_price_sum' => new Expression('SUM(o.price)')
        ]);

        $select->join(
            ['o' => PREFIX_DB . LEND_PREFIX . LendObject::TABLE],
            'o.' . LendCategory::PK . '=c.' . LendCategory::PK,
            [],
            $select::JOIN_LEFT
        );

        //categories of filtered objects only
        if ($this->filters->objects_filters instanceof ObjectsList) {
            $objects = new Objects(
                $this->zdb,
                $this->preferences,
                $this->login,
                new LendPreferences($this->preferences),
                $this->filters->objects_filters
            );
            $objects->applyFilters($select);
        }

        $this->whereActive($select, $this->filters->active_filter);

        if ((string)$this->filters->filter_str !== '') {
            $select->where($this->contains('c.name', $this->filters->filter_str));
        }

        $select->group('c.' . self::PK);

        if ($this->filters->not_empty === true) {
            $select->having(new PredicateExpression('COUNT(o.' . LendObject::PK . ') > 0'));
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
            self::ORDERBY_NAME => 'c.name',
            self::ORDERBY_ACTIVITY => 'c.is_active',
            default => null
        };

        return $field === null ? [] : [$field . ' ' . $this->filters->getDirection()];
    }

    /**
     * Create an entity from a resultset row
     *
     * @param ArrayObject<string,mixed> $row Resultset row
     */
    protected function createEntity(ArrayObject $row): LendCategory
    {
        return new LendCategory($this->zdb, $row);
    }
}
