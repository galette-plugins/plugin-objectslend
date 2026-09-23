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
use Galette\Entity\Adherent;
use GaletteObjectsLend\Filters\ObjectsList;
use GaletteObjectsLend\Entity\Preferences;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use Laminas\Db\Sql\Select;

/**
 * Objects list
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Objects
{
    public const string TABLE = LendObject::TABLE;
    public const string PK = LendObject::PK;

    public const int ALL_OBJECTS = 0;
    public const int ACTIVE_OBJECTS = 1;
    public const int INACTIVE_OBJECTS = 2;

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

    private Db $zdb;

    private ObjectsList $filters;
    private ?int $count = null;
    /** @var array<string> */
    private array $errors = [];
    private Preferences $prefs;

    /**
     * Default constructor
     *
     * @param Db           $zdb     Database instance
     * @param Preferences  $lprefs  Lends preferences instance
     * @param ?ObjectsList $filters Filtering
     */
    public function __construct(Db $zdb, Preferences $lprefs, ?ObjectsList $filters = null)
    {
        $this->zdb = $zdb;
        $this->prefs = $lprefs;

        if ($filters === null) {
            $this->filters = new ObjectsList();
        } else {
            $this->filters = $filters;
        }
    }

    /**
     * Get objects list
     *
     * @param bool           $as_objects return the results as an array of
     *                                   Object object.
     * @param ?array<string> $fields     field(s) name(s) to get. If null, all fields will be returned
     * @param bool           $count      true if we want to count members
     * @param bool           $limit      true if we want records pagination
     *
     * @return LendObject[]|ResultSet
     */
    public function getObjectsList(
        bool $as_objects = false,
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

            $objects = [];
            if ($as_objects) {
                foreach ($rows as $row) {
                    $objects[] = new LendObject($this->zdb, $row);
                }
            } else {
                $objects = $rows;
            }
            return $objects;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list objects | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Remove specified objects, and their full history
     *
     * @param array<int> $ids Objects identifiers to delete
     */
    public function removeObjects(array $ids): bool
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }

            $update = $this->zdb->update(LEND_PREFIX . self::TABLE);
            $update->set(['rent_id' => null]);
            $update->where->in(
                self::PK,
                $ids
            );
            $this->zdb->execute($update);

            $delete = $this->zdb->delete(LEND_PREFIX . LendRent::TABLE);
            $delete->where->in(
                self::PK,
                $ids
            );
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(LEND_PREFIX . self::TABLE);
            $delete->where->in(
                self::PK,
                $ids
            );
            $this->zdb->execute($delete);
            if ($need_transaction) {
                $this->zdb->commit();
            }
            return true;
        } catch (\Exception $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }

            if ($this->zdb->isForeignKeyException($e)) {
                Analog::log(
                    'Object mays still have existing dependencies in the '
                    . 'database.'
                    . 'Please remove dependencies before trying '
                    . 'to remove it.',
                    Analog::ERROR
                );
            } else {
                Analog::log(
                    'Unable to delete selected object(s) |'
                    . $e->getMessage(),
                    Analog::ERROR
                );
            }
        }

        return false;
    }

    /**
     * Get Objects list
     *
     * @param bool           $as_objects return the results as an array of
     *                                   Object object.
     * @param ?array<string> $fields     field(s) name(s) to get. If null, all fields will be returned
     *
     * @return LendObject[]|ResultSet
     */
    public function getList(bool $as_objects = false, ?array $fields = null): array|ResultSet
    {
        return $this->getObjectsList(
            $as_objects,
            $fields,
            false,
            false
        );
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
     *
     * @param ?string[] $fields fields list to retrieve
     * @param bool      $count  true if we want to count members, defaults to false
     *
     * @return Select SELECT statement
     */
    private function buildSelect(?array $fields, bool $count = false): Select
    {
        try {
            $select = $this->buildBaseSelect();

            $fieldsList = ($fields != null)
                            ? ((!is_array($fields) || count($fields) < 1) ? (array)'*'
                            : $fields) : (array)'*';

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
                'Cannot build SELECT clause for objects | ' . $e->getMessage(),
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
            $countSelect->reset($countSelect::JOINS);
            $countSelect->columns(
                [
                    'count' => new Expression('count(o.' . self::PK . ')')
                ]
            );

            $have = $select->having;
            if ($have->count() > 0) {
                foreach ($have->getPredicates() as $h) {
                    $countSelect->where($h);
                }
            }

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

            $this->count = (int)$results->current()->count;
            if ($this->count > 0) {
                $this->filters->setCounter($this->count);
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot count objects | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @param array<string> $fields Fields list to ensure ORDER clause
     *                              references selected fields. Optional.
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(?array $fields = null): array
    {
        $order = [];
        switch ($this->filters->orderby) {
            case self::ORDERBY_NAME:
                if ($this->canOrderBy('name', $fields)) {
                    $order[] = 'o.name ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_SERIAL:
                if ($this->canOrderBy('serial_number', $fields)) {
                    $order[] = 'o.serial_number ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_PRICE:
                if ($this->canOrderBy('price', $fields)) {
                    $order[] = 'o.price ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_RENTPRICE:
                if ($this->canOrderBy('rent_price', $fields)) {
                    $order[] = 'o.rent_price ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_WEIGHT:
                if ($this->canOrderBy('weight', $fields)) {
                    $order[] = 'o.weight ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_STATUS:
                if ($this->canOrderBy('status_text', $fields)) {
                    $order[] = 's.status_text ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_BDATE:
                if ($this->canOrderBy('date_begin', $fields)) {
                    $order[] = 'r.date_begin ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_FDATE:
                if ($this->canOrderBy('date_forecast', $fields)) {
                    $order[] = 'r.date_forecast ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_MEMBER:
                if ($this->canOrderBy('nom_adh', $fields) && $this->canOrderBy('prenom_adh', $fields)) {
                    $order[] = 'a.nom_adh ' . $this->filters->getDirection()
                        . ', a.prenom_adh ' . $this->filters->getDirection();
                }
                break;
            case self::ORDERBY_CATEGORY:
                if ($this->canOrderBy('cat_name', $fields)) {
                    $order[] = 'c.name ' . $this->filters->getDirection();
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
    public function buildWhereClause(Select $select): void
    {
        try {
            if (is_array($this->filters->selected) && count($this->filters->selected) > 0) {
                $select->where->in('o.' . self::PK, $this->filters->selected);
            }

            if ($this->filters->active_filter == self::ACTIVE_OBJECTS) {
                $select->where('(o.is_active = true AND (c.is_active IS NULL OR c.is_active = true))');
            }
            if ($this->filters->active_filter == self::INACTIVE_OBJECTS) {
                $select->where('(o.is_active = false OR (c.is_active IS NOT NULL AND c.is_active = false))');
            }

            if ($this->filters->category_filter == '-1') {
                $select->where('o.' . LendCategory::PK . ' IS NULL');
            } elseif ($this->filters->category_filter !== null) {
                $select->where('o.' . LendCategory::PK . '=' . $this->filters->category_filter);
            }

            if ($this->filters->filter_str != '') {
                $token = $this->zdb->platform->quoteValue(
                    '%' . strtolower($this->filters->filter_str) . '%'
                );

                switch ($this->filters->field_filter) {
                    case self::FILTER_NAME:
                        if (TYPE_DB === 'pgsql') {
                            $sep = " || ' ' || ";
                            $pre = '';
                            $post = '';
                        } else {
                            $sep = ', " ", ';
                            $pre = 'CONCAT(';
                            $post = ')';
                        }

                        if ($this->prefs->getPreferences()['VIEW_DESCRIPTION']) {
                            $select->where(
                                '('
                                . $pre . 'LOWER(o.name)' . $sep
                                . 'LOWER(o.description)' . $post . ' LIKE '
                                . $token . ')'
                            );
                        } else {
                            $select->where(
                                'LOWER(o.name) LIKE ' . $token
                            );
                        }
                        break;
                    case self::FILTER_SERIAL:
                        $select->where(
                            'LOWER(o.serial_number) LIKE '
                            . $token
                        );
                        break;
                    case self::FILTER_DIM:
                        $select->where(
                            'LOWER(o.dimension) LIKE '
                            . $token
                        );
                        break;
                    case self::FILTER_ID:
                        $select->where->equalTo('o.' . LendObject::PK, $this->filters->filter_str);
                        break;
                }
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
}
