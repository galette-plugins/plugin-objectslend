<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters;

use Galette\Core\Pagination;
use GaletteObjectsLend\Repository\Status;

/**
 * Status list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $filter_str
 * @property ?int    $active_filter
 * @property ?int    $stock_filter
 */
class StatusList extends Pagination
{
    use ListFilters;

    private ?int $stock_filter = null;

    /**
     * Returns the field we want to default set order to
     */
    protected function getDefaultOrder(): int|string
    {
        return Status::ORDERBY_NAME;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->reinitListFilters();
        $this->stock_filter = null;
    }

    /**
     * Filtering properties of the class, besides filter_str and active_filter
     *
     * @return array<string>
     */
    protected function getOwnFilters(): array
    {
        return ['stock_filter'];
    }

    /**
     * Set a filtering property of the class
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    protected function setOwnFilter(string $name, mixed $value): bool
    {
        if ($name !== 'stock_filter') {
            return false;
        }
        $this->stock_filter = $this->toChoice(
            $name,
            $value,
            [Status::DC_STOCK, Status::IN_STOCK, Status::OUT_STOCK]
        ) ?? $this->stock_filter;
        return true;
    }
}
