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
    //filters
    private ?string $filter_str;
    private ?int $active_filter;
    private ?int $stock_filter;


    /** @var array<string> */
    protected array $statuslist_fields = [
        'filter_str',
        'active_filter',
        'stock_filter'
    ];

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
        $this->filter_str = null;
        $this->active_filter = null;
        $this->stock_filter = null;
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
            if (in_array($name, $this->statuslist_fields)) {
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
                '[StatusList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'filter_str':
                    $this->$name = $value;
                    break;
                case 'active_filter':
                    switch ($value) {
                        case Status::ALL:
                        case Status::ACTIVE:
                        case Status::INACTIVE:
                            $this->active_filter = (int)$value;
                            break;
                        default:
                            Analog::log(
                                '[StatusList] Value for active filter should be either '
                                . Status::ACTIVE . ' or '
                                . Status::INACTIVE . ' (' . $value . ' given)',
                                Analog::WARNING
                            );
                            break;
                    }
                    break;
                case 'stock_filter':
                    switch ($value) {
                        case Status::DC_STOCK:
                        case Status::IN_STOCK:
                        case Status::OUT_STOCK:
                            $this->stock_filter = (int)$value;
                            break;
                        default:
                            Analog::log(
                                '[StatusList] Value for stock filter should be either '
                                . Status::IN_STOCK . ', ' . Status::OUT_STOCK . ' or '
                                . Status::DC_STOCK . ' (' . $value . ' given)',
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
}
