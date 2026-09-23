<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use GaletteObjectsLend\Repository\Categories;

/**
 * Categories list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string      $filter_str
 * @property ?int         $active_filter
 * @property ?bool        $not_empty
 * @property ?ObjectsList $objects_filters
 */

class CategoriesList extends Pagination
{
    //filters
    private ?string $filter_str;
    private ?int $active_filter;
    private ?bool $not_empty;
    private ?ObjectsList $objects_filters;


    /** @var array<string> */
    protected array $categorylist_fields = [
        'filter_str',
        'active_filter',
        'not_empty',
        'objects_filters'
    ];

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     */
    protected function getDefaultOrder(): int|string
    {
        return Categories::ORDERBY_NAME;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->filter_str = null;
        $this->active_filter = null;
        $this->not_empty = null;
        $this->objects_filters = null;
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
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->categorylist_fields)) {
                return $this->$name;
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {

        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[CategoriesList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'filter_str':
                case 'not_empty':
                    $this->$name = $value;
                    break;
                case 'active_filter':
                    switch ($value) {
                        case Categories::ALL_CATEGORIES:
                        case Categories::ACTIVE_CATEGORIES:
                        case Categories::INACTIVE_CATEGORIES:
                            $this->active_filter = (int)$value;
                            break;
                        default:
                            Analog::log(
                                '[CategoriesList] Value for active filter should be either '
                                . Categories::ALL_CATEGORIES . ', ' . Categories::ACTIVE_CATEGORIES . ' or '
                                . Categories::INACTIVE_CATEGORIES . ' (' . $value . ' given)',
                                Analog::WARNING
                            );
                            break;
                    }
                    break;
                default:
                    throw new \RuntimeException(
                        sprintf(
                            'Unable to set property "%s::%s"!',
                            __CLASS__,
                            $name
                        )
                    );
            }
        }
    }

    /**
     * Set objects filter
     *
     * @param ObjectsList $filters Filters for objects list
     */
    public function setObjectsFilter(ObjectsList $filters): self
    {
        $this->objects_filters = $filters;
        return $this;
    }
}
