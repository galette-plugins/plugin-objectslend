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
use Galette\Entity\Adherent;
use Galette\Util\Html;
use GaletteObjectsLend\Filters\ObjectsList;
use GaletteObjectsLend\Repository\Objects;

/**
 * Object
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?int          $object_id
 * @property ObjectPicture $picture
 * @property string        $name
 * @property string        $description
 * @property string        $serial_number
 * @property float         $price
 * @property float         $rent_price
 * @property float         $value_rent_price
 * @property bool          $price_per_day
 * @property string        $dimension
 * @property float         $weight
 * @property bool          $is_active
 * @property string        $cat_name
 * @property string        $status_text
 * @property string        $date_begin
 * @property ?Adherent     $member
 * @property string        $date_forecast
 * @property ?LendRent[]   $rents
 * @property int           $category_id
 * @property string        $nom_adh
 * @property string        $prenom_adh
 * @property string        $currency
 * @property bool          $in_stock
 */
class LendObject
{
    public const string TABLE = 'objects';
    public const string PK = 'object_id';

    /** @var array<string,string> */
    private array $fields = [
        'object_id' => 'integer',
        'name' => 'varchar(100)',
        'description' => 'varchar(500)',
        'serial_number' => 'varchar(30)',
        'price' => 'decimal',
        'rent_price' => 'decimal',
        'price_per_day' => 'boolean',
        'dimension' => 'varchar(100)',
        'weight' => 'decimal',
        'is_active' => 'boolean',
        'category_id' => 'int',
        'nb_available' => 'int',
    ];
    private ?int $object_id;
    private string $name = '';
    private string $description = '';
    private string $serial_number = '';
    private float $price = 0.0;
    private float $rent_price = 0.0;
    private bool $price_per_day = false;
    private string $dimension = '';
    private float $weight = 0.0;
    private bool $is_active = true;
    private ?int $category_id;
    private int $nb_available = 1;

    private string $date_begin;
    private ?string $date_forecast;
    private ?string $date_end;
    private string $status_text = '';
    private int $status_id;
    private bool $in_stock = true;

    private string $nom_adh = '';
    private string $prenom_adh = '';
    private string $email_adh = '';
    private ?int $id_adh;
    private ?int $rent_id;
    private string $comments = '';
    private string $currency = '€';
    private ObjectPicture $picture;
    private bool $cat_active = true;
    private ?string $cat_name = null;
    private Adherent $member;

    /** @var array<string,bool> */
    private array $deps = [
        'picture'   => true,
        'rents'     => false,
        'last_rent' => false,
        'status'    => false,
        'member'    => false,
        'category'  => false
    ];

    private Db $zdb;

    /**
     * @var LendRent[]
     * Rents list for the object
     */
    private array $rents;

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param int|ArrayObject<string,int|string>|null $args Maybe null, an RS object or an id from database
     * @param ?array<string,bool>                     $deps Dependencies configuration, see LendOb::$deps
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
            $this->picture = new ObjectPicture();
        }

        if (is_int($args)) {
            try {
                $select = $this->zdb->select(LEND_PREFIX . self::TABLE, 'o');

                if ($this->deps['status'] || $this->deps['last_rent'] === true) {
                    if ($this->deps['last_rent'] === true) {
                        $fields = ['date_begin', 'date_forecast', 'date_end', 'comments'];
                    } else {
                        $fields = [];
                    }
                    $select->join(
                        ['r' => PREFIX_DB . LEND_PREFIX . LendRent::TABLE],
                        'o.' . LendRent::PK . '=r.' . LendRent::PK,
                        $fields,
                        $select::JOIN_LEFT
                    );
                }

                if ($this->deps['status'] === true) {
                    $select->join(
                        ['s' => PREFIX_DB . LEND_PREFIX . LendStatus::TABLE],
                        'r.' . LendStatus::PK . '=s.' . LendStatus::PK,
                        ['status_id', 'status_text', 'in_stock'],
                        $select::JOIN_LEFT
                    );
                }

                if ($this->deps['member'] === true) {
                    $select->join(
                        ['a' => PREFIX_DB . Adherent::TABLE],
                        'r.adherent_id=a.' . Adherent::PK,
                        [Adherent::PK, 'nom_adh', 'prenom_adh'],
                        $select::JOIN_LEFT
                    );
                }

                if ($this->deps['category'] === true) {
                    $select->join(
                        ['c' => PREFIX_DB . LEND_PREFIX . LendCategory::TABLE],
                        'o.' . LendCategory::PK . '=c.' . LendCategory::PK,
                        ['cat_active'   => 'is_active', 'cat_name' => 'name'],
                        $select::JOIN_LEFT
                    );
                }

                $select->where(['o.' . self::PK => $args]);
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
     * @param ArrayObject<string,int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->object_id = (int)$r->object_id;
        $this->name = $r->name;
        $this->description = $r->description;
        $this->serial_number = $r->serial_number;
        $this->price = is_numeric($r->price) ? (float)$r->price : 0.0;
        $this->rent_price = is_numeric($r->rent_price) ? (float)$r->rent_price : 0.0;
        $this->price_per_day = $r->price_per_day == '1';
        $this->dimension = $r->dimension;
        $this->weight = is_numeric($r->weight) ? (float)$r->weight : 0.0;
        $this->is_active = $r->is_active == '1';
        if (property_exists($r, 'cat_active') && ($r->cat_active == 1 || $r->cat_active === null)) {
            $this->cat_active = true;
        } else {
            $this->cat_active = false;
        }
        if (property_exists($r, 'cat_name') && $r->cat_name) {
            $this->cat_name = $r->cat_name;
        }
        if ($r->category_id != null) {
            $this->category_id = (int)$r->category_id;
        }
        $this->nb_available = (int)$r->nb_available;
        if ($r->rent_id != null) {
            $this->rent_id = (int)$r->rent_id;
        }

        //load last rent infos (status, member, and so on
        if (isset($this->rent_id)) {
            if (property_exists($r, 'status_id')) {
                $this->status_id = (int)$r->status_id;
            }

            if (property_exists($r, 'status_text')) {
                $this->status_text = $r->status_text;
            }

            if (property_exists($r, 'date_begin')) {
                $this->date_begin = $r->date_begin;
            }

            if (property_exists($r, 'date_end')) {
                $this->date_end = $r->date_end;
            }

            if (property_exists($r, 'date_forecast')) {
                $this->date_forecast = $r->date_forecast;
            }

            if (property_exists($r, Adherent::PK)) {
                $this->id_adh = (int)$r->{Adherent::PK};
            }

            if (property_exists($r, 'in_stock')) {
                $this->in_stock = (bool)$r->in_stock;
            }
        }

        if ($this->object_id && $this->deps['rents'] === true) {
            $this->rents = LendRent::getRentsForObjectId($this->object_id);
        }

        if ($this->deps['picture'] === true) {
            $this->picture = new ObjectPicture((int)$this->object_id);
        }

        if ($this->deps['member'] === true) {
            $this->member = new Adherent(
                $this->zdb,
                (int)$r->id_adh,
                [
                    'picture'   => false,
                    'groups'    => false,
                    'dues'      => false,
                ]
            );
        }
    }

    /**
     * Store object
     */
    public function store(): bool
    {
        try {
            $values = [];

            foreach (array_keys($this->fields) as $k) {
                if (
                    ($k === 'is_active' || $k === 'price_per_day')
                    && $this->$k === false
                ) {
                    //Handle booleans for postgres ; bugs #18899 and #19354
                    $values[$k] = $this->zdb->isPostgres() ? 'false' : 0;
                } else {
                    $values[$k] = $this->$k ?? null;
                }
            }

            if (!isset($this->object_id) || $this->object_id == '') {
                unset($values[self::PK]);
                $insert = $this->zdb->insert(LEND_PREFIX . self::TABLE)
                        ->values($values);
                $result = $this->zdb->execute($insert);
                if ($result->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->object_id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . 'lend_objects_id_seq'
                        );
                    } else {
                        $this->object_id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    if ($this->deps['picture'] === true) {
                        $this->picture = new ObjectPicture((int)$this->object_id);
                    }
                } else {
                    throw new \Exception(_T("Object has not been added :(", "objectslend"));
                }
            } else {
                $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                        ->set($values)
                        ->where([self::PK => $this->object_id]);
                $this->zdb->execute($update);
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                    . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'date_begin':
            case 'date_forecast':
                return $this->getDateField($name);
            case 'price':
            case 'rent_price':
                return number_format($this->$name, 2, ',', ' ');
            case 'value_rent_price':
                return $this->rent_price;
            case 'weight':
                return number_format($this->weight, 3, ',', ' ');
            default:
                return $this->$name ?? null;
        }
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        $forbidden = ['currency'];
        if (!in_array($name, $forbidden)) {
            switch ($name) {
                case 'category_id':
                    if ($value == '') {
                        $value = null;
                    } else {
                        $value = (int)$value;
                    }
                    //no break for value to be set in default
                default:
                    $this->$name = $value;
                    break;
            }
        }
    }

    /**
     * Get currency
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get current rent
     */
    public function getCurrentRent(): ?LendRent
    {
        if (isset($this->rents) && is_array($this->rents) && count($this->rents) > 0) {
            return $this->rents[0];
        }
        return null;
    }

    /**
     * Is current object active?
     *
     * Check for activity from object and from its parent category if any
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->cat_active;
    }

    /**
     * Get highlighted string
     *
     * @param ObjectsList $filters Filters
     * @param string      $field   Field name
     */
    private function getHighlighted(ObjectsList $filters, string $field): string
    {
        //check if search concerns field
        $process = false;
        switch ($field) {
            case 'description':
            case 'name':
                if ($filters->field_filter == Objects::FILTER_NAME) {
                    $process = true;
                }
                break;
            case 'serial_number':
                if ($filters->field_filter == Objects::FILTER_SERIAL) {
                    $process = true;
                }
                break;
            case 'dimension':
                if ($filters->field_filter == Objects::FILTER_DIM) {
                    $process = true;
                }
                break;
            case 'object_id':
                if ($filters->field_filter === Objects::FILTER_ID) {
                    $process = true;
                }
                break;
        }

        if ($field === 'description') {
            $value = $this->getDescriptionHtml();
        } else {
            $value = htmlspecialchars((string)($this->$field ?? ''), ENT_QUOTES);
        }
        $search = trim($filters->filter_str ?? '', '%');
        if ($process === false || $search === '') {
            return $value;
        }

        //highlight text only, never inside HTML tags
        $parts = preg_split('/(<[^>]*>)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $value;
        }
        $pattern = '/(' . preg_quote(htmlspecialchars($search, ENT_QUOTES), '/') . ')/iu';
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }
            $parts[$i] = preg_replace($pattern, '<span class="search">$1</span>', $part) ?? $part;
        }
        return implode('', $parts);
    }

    /**
     * Get description as sanitized HTML
     *
     * Description may contain HTML, it must be cleaned before being displayed.
     */
    public function getDescriptionHtml(): string
    {
        return Html::clean($this->description ?? '');
    }

    /**
     * Displays name, with search terms highlighted
     *
     * @param ObjectsList $filters Filters
     */
    public function displayName(ObjectsList $filters): string
    {
        return $this->getHighlighted($filters, 'name');
    }

    /**
     * Displays description, with search terms highlighted
     *
     * @param ObjectsList $filters Filters
     */
    public function displayDescription(ObjectsList $filters): string
    {
        return $this->getHighlighted($filters, 'description');
    }

    /**
     * Displays serial number, with search terms highlighted
     *
     * @param ObjectsList $filters Filters
     */
    public function displaySerial(ObjectsList $filters): string
    {
        return $this->getHighlighted($filters, 'serial_number');
    }

    /**
     * Displays dimension, with search terms highlighted
     *
     * @param ObjectsList $filters Filters
     */
    public function displayDimension(ObjectsList $filters): string
    {
        return $this->getHighlighted($filters, 'dimension');
    }

    /**
     * Delete object
     */
    public function delete(): bool
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }
            //remove rents
            $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                    ->set([LendRent::PK => null])
                    ->where([self::PK => $this->object_id]);
            $this->zdb->execute($update);
            $delete = $this->zdb->delete(LEND_PREFIX . LendRent::TABLE)
                    ->where([self::PK => $this->object_id]);
            $this->zdb->execute($delete);
            $delete = $this->zdb->delete(LEND_PREFIX . self::TABLE)
                    ->where([self::PK => $this->object_id]);
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
            throw $e;
        }
    }

    /**
     * Clone object
     */
    public function clone(): bool
    {
        //unset id so this is considered as new object
        unset($this->object_id);
        //unset image
        $this->picture = new ObjectPicture();
        return $this->store();
    }

    /**
     * Get ID
     */
    public function getId(): ?int
    {
        return $this->object_id ?? null;
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get picture
     */
    public function getPicture(): ObjectPicture
    {
        return $this->picture;
    }

    /**
     * Get price
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * Get rent price
     */
    public function getRentPrice(): float
    {
        return $this->rent_price;
    }

    /**
     * Is a price per day
     */
    public function isPricePerDay(): bool
    {
        return $this->price_per_day;
    }

    /**
     * Get weight
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * Get textual status
     */
    public function getStatusText(): string
    {
        return $this->status_text;
    }

    /**
     * Is in stock
     */
    public function inStock(): bool
    {
        return $this->in_stock;
    }

    /**
     * Get localized begin date
     *
     * @throws \Exception
     */
    public function getDateBegin(): string
    {
        return $this->getDateField('date_begin');
    }

    /**
     * Get localized forecast date
     *
     * @throws \Exception
     */
    public function getDateForecast(): string
    {
        return $this->getDateField('date_forecast');
    }

    /**
     * Get member ID
     */
    public function getIdAdh(): ?int
    {
        return $this->id_adh ?? null;
    }

    /**
     * Get rent ID
     */
    public function getRentId(): ?int
    {
        return $this->rent_id ?? null;
    }

    /**
     * Get category ID
     */
    public function getCategoryId(): ?int
    {
        return $this->category_id ?? null;
    }

    /**
     * Get serial number
     */
    public function getSerialNumber(): string
    {
        return $this->serial_number;
    }

    /**
     * Get localized date field
     *
     * @param string $name Field name
     *
     * @throws \Exception
     */
    protected function getDateField(string $name): string
    {
        $date = $this->$name ?? null;
        if ($date == '' || $date == null) {
            return '';
        }
        $datetime = new \DateTime($date);
        return $datetime->format(_T('Y-m-d'));
    }

    /**
     * Generic isset function
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }
}
