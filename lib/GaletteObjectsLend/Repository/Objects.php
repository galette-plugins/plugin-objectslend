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
use Galette\Entity\Adherent;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\Filters\ObjectsList;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;

/**
 * Objects list
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractRepository<LendObject>
 */
class Objects extends AbstractRepository
{
    public const string TABLE = LendObject::TABLE;
    public const string PK = LendObject::PK;
    protected const string ALIAS = 'o';

    public const int ALL_OBJECTS = self::ALL;
    public const int ACTIVE_OBJECTS = self::ACTIVE;
    public const int INACTIVE_OBJECTS = self::INACTIVE;

    public const int FILTER_NAME = 0;
    public const int FILTER_SERIAL = 1;
    public const int FILTER_DIM = 2;
    public const int FILTER_ID = 3;

    public const int ORDERBY_NAME = 0;
    public const int ORDERBY_SERIAL = 1;
    public const int ORDERBY_PRICE = 2;
    public const int ORDERBY_RENTPRICE = 3;
    public const int ORDERBY_WEIGHT = 4;
    public const int ORDERBY_STATUS = 5;
    public const int ORDERBY_BDATE = 6;
    public const int ORDERBY_FDATE = 7;
    public const int ORDERBY_MEMBER = 8;
    public const int ORDERBY_CATEGORY = 9;

    /** @var ObjectsList */
    protected \Galette\Core\Pagination $filters;

    /**
     * Default constructor
     *
     * @param Db              $zdb         Database instance
     * @param Preferences     $preferences Preferences instance
     * @param Login           $login       Logged in instance
     * @param LendPreferences $lendsprefs  Lends preferences instance
     * @param ?ObjectsList    $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Preferences $preferences,
        Login $login,
        private LendPreferences $lendsprefs,
        ?ObjectsList $filters = null
    ) {
        parent::__construct($zdb, $preferences, $login, 'LendObject', $filters ?? new ObjectsList());
    }

    /**
     * Get objects list
     *
     * @param bool $as_objects return the results as an array of Object object.
     * @param bool $count      true if we want to count rows
     * @param bool $limit      true if we want records pagination
     *
     * @return ($as_objects is true ? LendObject[] : ResultSet)
     */
    public function getObjectsList(bool $as_objects = false, bool $count = true, bool $limit = true): array|ResultSet
    {
        return $this->fetchList($as_objects, $count, $limit);
    }

    /**
     * Get whole objects list
     *
     * @param bool $as_objects return the results as an array of Object object.
     *
     * @return ($as_objects is true ? LendObject[] : ResultSet)
     */
    public function getList(bool $as_objects = false): array|ResultSet
    {
        return $this->fetchList($as_objects, false, false);
    }

    /**
     * Remove specified objects, and their full history
     *
     * @param array<int> $ids Objects identifiers to delete
     */
    public function removeObjects(array $ids): void
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }

            $update = $this->zdb->update(LEND_PREFIX . self::TABLE);
            $update->set(['rent_id' => null]);
            $update->where->in(self::PK, $ids);
            $this->zdb->execute($update);

            $delete = $this->zdb->delete(LEND_PREFIX . LendRent::TABLE);
            $delete->where->in(self::PK, $ids);
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(LEND_PREFIX . self::TABLE);
            $delete->where->in(self::PK, $ids);
            $this->zdb->execute($delete);

            if ($need_transaction) {
                $this->zdb->commit();
            }
        } catch (\Throwable $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Builds the SELECT statement for objects with their current rent and their category
     */
    private function buildBaseSelect(): Select
    {
        $select = $this->zdb->select(LEND_PREFIX . self::TABLE, 'o');

        $select->join(
            ['r' => PREFIX_DB . LEND_PREFIX . LendRent::TABLE],
            'o.' . LendRent::PK . '=r.' . LendRent::PK,
            ['date_begin', 'date_forecast', 'date_end', 'comments'],
            $select::JOIN_LEFT
        );

        $select->join(
            ['s' => PREFIX_DB . LEND_PREFIX . LendStatus::TABLE],
            'r.' . LendStatus::PK . '=s.' . LendStatus::PK,
            ['status_id', 'status_text', 'in_stock'],
            $select::JOIN_LEFT
        );

        $select->join(
            ['a' => PREFIX_DB . Adherent::TABLE],
            'r.adherent_id=a.' . Adherent::PK,
            [Adherent::PK, 'nom_adh', 'prenom_adh'],
            $select::JOIN_LEFT
        );

        $select->join(
            ['c' => PREFIX_DB . LEND_PREFIX . LendCategory::TABLE],
            'o.' . LendCategory::PK . '=c.' . LendCategory::PK,
            ['cat_active'   => 'is_active', 'cat_name' => 'name'],
            $select::JOIN_LEFT
        );

        return $select;
    }

    /**
     * Get an object with its current rent and its category
     *
     * @param int $id Object ID
     */
    public function getWithCurrentRent(int $id): LendObject
    {
        $select = $this->buildBaseSelect();
        $select->where(['o.' . self::PK => $id]);
        $results = $this->zdb->execute($select);
        if ($results->count() === 1) {
            return new LendObject($this->zdb, $results->current());
        }
        return new LendObject($this->zdb);
    }

    /**
     * Builds the SELECT statement
     */
    protected function buildSelect(): Select
    {
        $select = $this->buildBaseSelect();
        $this->applyFilters($select);
        return $select;
    }

    /**
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    protected function buildOrderClause(): array
    {
        $fields = match ($this->filters->orderby) {
            self::ORDERBY_NAME => ['o.name'],
            self::ORDERBY_SERIAL => ['o.serial_number'],
            self::ORDERBY_PRICE => ['o.price'],
            self::ORDERBY_RENTPRICE => ['o.rent_price'],
            self::ORDERBY_WEIGHT => ['o.weight'],
            self::ORDERBY_STATUS => ['s.status_text'],
            self::ORDERBY_BDATE => ['r.date_begin'],
            self::ORDERBY_FDATE => ['r.date_forecast'],
            self::ORDERBY_MEMBER => ['a.nom_adh', 'a.prenom_adh'],
            self::ORDERBY_CATEGORY => ['c.name'],
            default => []
        };

        $direction = $this->filters->getDirection();
        return array_map(fn(string $field) => $field . ' ' . $direction, $fields);
    }

    /**
     * Create an entity from a resultset row
     *
     * @param ArrayObject<string,mixed> $row Resultset row
     */
    protected function createEntity(ArrayObject $row): LendObject
    {
        return new LendObject($this->zdb, $row);
    }

    /**
     * Add filters on objects
     *
     * Objects table must be aliased as "o", and categories one as "c".
     *
     * @param Select $select Select
     */
    public function applyFilters(Select $select): void
    {
        if (count($this->filters->selected) > 0) {
            $select->where->in('o.' . self::PK, $this->filters->selected);
        }

        //object and its category, if any, must be active
        if ($this->filters->active_filter === self::ACTIVE) {
            $select->where
                ->equalTo('o.is_active', 1)
                ->nest()
                    ->isNull('c.is_active')
                    ->or
                    ->equalTo('c.is_active', 1)
                ->unnest();
        } elseif ($this->filters->active_filter === self::INACTIVE) {
            $select->where
                ->nest()
                    ->equalTo('o.is_active', 0)
                    ->or
                    ->equalTo('c.is_active', 0)
                ->unnest();
        }

        if ($this->filters->category_filter === -1) {
            $select->where->isNull('o.' . LendCategory::PK);
        } elseif ($this->filters->category_filter !== null) {
            $select->where->equalTo('o.' . LendCategory::PK, $this->filters->category_filter);
        }

        $search = (string)$this->filters->filter_str;
        if ($search === '') {
            return;
        }

        switch ($this->filters->field_filter) {
            case self::FILTER_NAME:
                if ($this->lendsprefs->isEnabled(LendPreferences::VIEW_DESCRIPTION)) {
                    $select->where
                        ->nest()
                            ->addPredicate($this->contains('o.name', $search))
                            ->orPredicate($this->contains('o.description', $search))
                        ->unnest();
                } else {
                    $select->where($this->contains('o.name', $search));
                }
                break;
            case self::FILTER_SERIAL:
                $select->where($this->contains('o.serial_number', $search));
                break;
            case self::FILTER_DIM:
                $select->where($this->contains('o.dimension', $search));
                break;
            case self::FILTER_ID:
                $select->where->equalTo('o.' . self::PK, (int)$search);
                break;
        }
    }
}
