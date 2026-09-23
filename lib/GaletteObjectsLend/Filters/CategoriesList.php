<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters;

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
    use ListFilters;

    private ?bool $not_empty = null;
    private ?ObjectsList $objects_filters = null;

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
        $this->reinitListFilters();
        $this->not_empty = null;
        $this->objects_filters = null;
    }

    /**
     * Filtering properties of the class, besides filter_str and active_filter
     *
     * @return array<string>
     */
    protected function getOwnFilters(): array
    {
        return ['not_empty', 'objects_filters'];
    }

    /**
     * Set a filtering property of the class
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    protected function setOwnFilter(string $name, mixed $value): bool
    {
        if ($name !== 'not_empty') {
            return false;
        }
        $this->not_empty = $value === null ? null : (bool)$value;
        return true;
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
