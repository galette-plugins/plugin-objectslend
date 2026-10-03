<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters;

use Analog\Analog;
use GaletteObjectsLend\Repository\AbstractRepository;

/**
 * Filters shared by objects, categories and statuses lists: text search and activity
 *
 * Classes using it extend Galette\Core\Pagination.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait ListFilters
{
    private ?string $filter_str = null;
    private ?int $active_filter = null;

    /**
     * Filtering properties of the class, besides filter_str and active_filter
     *
     * @return array<string>
     */
    abstract protected function getOwnFilters(): array;

    /**
     * Set a filtering property of the class
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     *
     * @return bool false if property is unknown
     */
    abstract protected function setOwnFilter(string $name, mixed $value): bool;

    /**
     * Reinit shared filters
     */
    protected function reinitListFilters(): void
    {
        $this->filter_str = null;
        $this->active_filter = null;
    }

    /**
     * Global isset method
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        return in_array($name, $this->pagination_fields) || in_array($name, $this->getFilterNames());
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
        }
        if (in_array($name, $this->getFilterNames())) {
            return $this->$name;
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                static::class,
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
            return;
        }

        switch ($name) {
            case 'filter_str':
                $this->filter_str = $value === null ? null : (string)$value;
                break;
            case 'active_filter':
                $this->active_filter = $this->toChoice(
                    $name,
                    $value,
                    [AbstractRepository::ALL, AbstractRepository::ACTIVE, AbstractRepository::INACTIVE]
                ) ?? $this->active_filter;
                break;
            default:
                if (!$this->setOwnFilter($name, $value)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Unable to set property "%s::%s"!',
                            static::class,
                            $name
                        )
                    );
                }
        }
    }

    /**
     * Check a value is one of the allowed choices
     *
     * @param string     $name    Property name
     * @param mixed      $value   Value
     * @param array<int> $choices Allowed values
     *
     * @return ?int Value, null if it is not allowed
     */
    protected function toChoice(string $name, mixed $value, array $choices): ?int
    {
        if (is_numeric($value) && in_array((int)$value, $choices, true)) {
            return (int)$value;
        }

        Analog::log(
            sprintf(
                '[%1$s] Value for %2$s should be one of %3$s (%4$s given)',
                static::class,
                $name,
                implode(', ', $choices),
                var_export($value, true)
            ),
            Analog::WARNING
        );
        return null;
    }

    /**
     * Names of all filtering properties
     *
     * @return array<string>
     */
    private function getFilterNames(): array
    {
        return array_merge(['filter_str', 'active_filter'], $this->getOwnFilters());
    }
}
