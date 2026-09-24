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
use Galette\Util\Html;

/**
 * Object
 *
 * Information on the current rent (status, member, dates) and on the category
 * are only available when the object has been loaded from the Objects repository.
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
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
    private ?int $object_id = null;
    private string $name = '';
    private string $description = '';
    private string $serial_number = '';
    private float $price = 0.0;
    private float $rent_price = 0.0;
    private bool $price_per_day = false;
    private string $dimension = '';
    private float $weight = 0.0;
    private bool $is_active = true;
    private ?int $category_id = null;
    private int $nb_available = 1;

    //current rent
    private ?int $rent_id = null;
    private ?string $date_begin = null;
    private ?string $date_forecast = null;
    private string $status_text = '';
    private bool $in_stock = true;
    private ?int $id_adh = null;
    private string $nom_adh = '';
    private string $prenom_adh = '';

    //category
    private bool $cat_active = true;
    private ?string $cat_name = null;

    private ?ObjectPicture $picture = null;

    /**
     * Default constructor
     *
     * @param Db                                 $zdb  Database instance
     * @param int|ArrayObject<string,mixed>|null $args Maybe null, an RS object or an id from database
     */
    public function __construct(private Db $zdb, int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $select = $this->zdb->select(LEND_PREFIX . self::TABLE)
                ->where([self::PK => $args]);
            $results = $this->zdb->execute($select);
            if ($results->count() == 1) {
                $this->loadFromRS($results->current());
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
        $this->object_id = (int)$r['object_id'];
        $this->name = (string)$r['name'];
        $this->description = (string)$r['description'];
        $this->serial_number = (string)$r['serial_number'];
        $this->price = is_numeric($r['price']) ? (float)$r['price'] : 0.0;
        $this->rent_price = is_numeric($r['rent_price']) ? (float)$r['rent_price'] : 0.0;
        $this->price_per_day = $r['price_per_day'] == '1';
        $this->dimension = (string)$r['dimension'];
        $this->weight = is_numeric($r['weight']) ? (float)$r['weight'] : 0.0;
        $this->is_active = $r['is_active'] == '1';
        $this->category_id = $r['category_id'] !== null ? (int)$r['category_id'] : null;
        $this->nb_available = (int)$r['nb_available'];
        $this->rent_id = $r['rent_id'] !== null ? (int)$r['rent_id'] : null;

        //category, when joined
        $this->cat_active = !isset($r['cat_active']) || $r['cat_active'] == '1';
        if (isset($r['cat_name']) && $r['cat_name'] !== '') {
            $this->cat_name = (string)$r['cat_name'];
        }

        //current rent, when joined
        if ($this->rent_id !== null) {
            if (isset($r['status_text'])) {
                $this->status_text = (string)$r['status_text'];
            }
            if (isset($r['in_stock'])) {
                $this->in_stock = (bool)$r['in_stock'];
            }
            if (isset($r['date_begin'])) {
                $this->date_begin = (string)$r['date_begin'];
            }
            if (isset($r['date_forecast'])) {
                $this->date_forecast = (string)$r['date_forecast'];
            }
            if (isset($r['id_adh'])) {
                $this->id_adh = (int)$r['id_adh'];
            }
            if (isset($r['nom_adh'])) {
                $this->nom_adh = (string)$r['nom_adh'];
            }
            if (isset($r['prenom_adh'])) {
                $this->prenom_adh = (string)$r['prenom_adh'];
            }
        }
    }

    /**
     * Store object
     */
    public function store(): void
    {
        $values = [];

        foreach (array_keys($this->fields) as $k) {
            if (
                ($k === 'is_active' || $k === 'price_per_day')
                && $this->$k === false
            ) {
                //Handle booleans for postgres ; bugs #18899 and #19354
                $values[$k] = $this->zdb->isPostgres() ? 'false' : 0;
            } else {
                $values[$k] = $this->$k;
            }
        }

        if ($this->object_id === null) {
            unset($values[self::PK]);
            $insert = $this->zdb->insert(LEND_PREFIX . self::TABLE)
                    ->values($values);
            $result = $this->zdb->execute($insert);
            if ($result->count() > 0) {
                if ($this->zdb->isPostgres()) {
                    // @phpstan-ignore arguments.count (laminas does not respect its own interfaces)
                    $this->object_id = (int)$this->zdb->driver->getLastGeneratedValue(
                        PREFIX_DB . 'lend_objects_id_seq'
                    );
                } else {
                    $this->object_id = (int)$this->zdb->driver->getLastGeneratedValue();
                }
                $this->picture = null;
            } else {
                throw new \Exception(_T("Object has not been added :(", "objectslend"));
            }
        } else {
            $update = $this->zdb->update(LEND_PREFIX . self::TABLE)
                    ->set($values)
                    ->where([self::PK => $this->object_id]);
            $this->zdb->execute($update);
        }
    }

    /**
     * Delete object
     */
    public function delete(): void
    {
        $need_transaction = !$this->zdb->inTransaction();
        try {
            if ($need_transaction) {
                $this->zdb->beginTransaction();
            }
            //a picture file removed here comes back from the database on rollback
            $picture = $this->getPicture();
            if ($picture->hasPicture() && !$picture->delete(false)) {
                throw new \RuntimeException('Unable to remove picture');
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
        } catch (\Exception $e) {
            if ($need_transaction) {
                $this->zdb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Clone object
     *
     * The copy has neither picture nor rents.
     */
    public function clone(): void
    {
        $this->object_id = null;
        $this->rent_id = null;
        $this->picture = null;
        $this->store();
    }

    /**
     * Get ID
     */
    public function getId(): ?int
    {
        return $this->object_id;
    }

    /**
     * Get name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name Name
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get raw description
     *
     * Description may contain HTML, use getDescriptionHtml() to display it.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get description as sanitized HTML
     */
    public function getDescriptionHtml(): string
    {
        return Html::clean($this->description);
    }

    /**
     * Set description
     *
     * @param string $description Description, may contain HTML
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get serial number
     */
    public function getSerialNumber(): string
    {
        return $this->serial_number;
    }

    /**
     * Set serial number
     *
     * @param string $serial_number Serial number
     */
    public function setSerialNumber(string $serial_number): self
    {
        $this->serial_number = $serial_number;
        return $this;
    }

    /**
     * Get price
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * Set price
     *
     * @param float $price Price
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Get rent price
     */
    public function getRentPrice(): float
    {
        return $this->rent_price;
    }

    /**
     * Set rent price
     *
     * @param float $rent_price Rent price
     */
    public function setRentPrice(float $rent_price): self
    {
        $this->rent_price = $rent_price;
        return $this;
    }

    /**
     * Is rent price per day?
     */
    public function isPricePerDay(): bool
    {
        return $this->price_per_day;
    }

    /**
     * Set whether rent price is per day
     *
     * @param bool $price_per_day Price per day
     */
    public function setPricePerDay(bool $price_per_day): self
    {
        $this->price_per_day = $price_per_day;
        return $this;
    }

    /**
     * Get dimension
     */
    public function getDimension(): string
    {
        return $this->dimension;
    }

    /**
     * Set dimension
     *
     * @param string $dimension Dimension
     */
    public function setDimension(string $dimension): self
    {
        $this->dimension = $dimension;
        return $this;
    }

    /**
     * Get weight
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * Set weight
     *
     * @param float $weight Weight
     */
    public function setWeight(float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Is object active?
     *
     * Check for activity from object and from its parent category if any
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->cat_active;
    }

    /**
     * Is object itself active, whatever its category?
     */
    public function isObjectActive(): bool
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
     * Get category ID
     */
    public function getCategoryId(): ?int
    {
        return $this->category_id;
    }

    /**
     * Set category ID
     *
     * @param ?int $category_id Category ID, null for none
     */
    public function setCategoryId(?int $category_id): self
    {
        $this->category_id = $category_id;
        return $this;
    }

    /**
     * Get category name
     */
    public function getCategoryName(): ?string
    {
        return $this->cat_name;
    }

    /**
     * Get picture
     */
    public function getPicture(): ObjectPicture
    {
        return $this->picture ??= new ObjectPicture($this->object_id);
    }

    /**
     * Get current rent ID
     */
    public function getRentId(): ?int
    {
        return $this->rent_id;
    }

    /**
     * Get current status text
     */
    public function getStatusText(): string
    {
        return $this->status_text;
    }

    /**
     * Is object in stock?
     */
    public function inStock(): bool
    {
        return $this->in_stock;
    }

    /**
     * Get localized begin date of current rent
     */
    public function getDateBegin(): string
    {
        return $this->formatDate($this->date_begin);
    }

    /**
     * Get localized forecast date of current rent
     */
    public function getDateForecast(): string
    {
        return $this->formatDate($this->date_forecast);
    }

    /**
     * Get ID of the member holding the object
     */
    public function getIdAdh(): ?int
    {
        return $this->id_adh;
    }

    /**
     * Get name of the member holding the object
     */
    public function getMemberName(): string
    {
        return trim($this->nom_adh . ' ' . $this->prenom_adh);
    }

    /**
     * Get localized date
     *
     * @param ?string $date Raw date
     */
    private function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        return (new \DateTime($date))->format(_T('Y-m-d'));
    }
}
