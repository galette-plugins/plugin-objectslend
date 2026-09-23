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
use GaletteObjectsLend\Entity\Preferences;
use GaletteObjectsLend\Repository\Objects;
use Slim\Views\Twig;

/**
 * Objects list filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string $filter_str
 * @property ?int    $category_filter
 * @property ?int    $active_filter
 * @property ?int    $field_filter
 * @property array   $selected
 */

class ObjectsList extends Pagination
{
    //filters
    private ?string $filter_str;
    private ?int $category_filter;
    private ?int $active_filter;
    private ?int $field_filter;
    /** @var array<int> */
    private array $selected;


    /** @var array<string> */
    protected array $objectslist_fields = [
        'filter_str',
        'category_filter',
        'active_filter',
        'field_filter',
        'selected'
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
        return Objects::ORDERBY_NAME;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->filter_str = null;
        $this->category_filter = null;
        $this->active_filter = null;
        $this->field_filter = null;
        $this->selected = [];
    }

    /**
     * Highlight search terms, when the search concerns the field
     *
     * @param string $html  Field value, as HTML: plain text must have been escaped
     * @param string $field Field name, one of name, description, serial_number or dimension
     */
    public function highlight(string $html, string $field): string
    {
        $fields = match ($this->field_filter) {
            Objects::FILTER_NAME => ['name', 'description'],
            Objects::FILTER_SERIAL => ['serial_number'],
            Objects::FILTER_DIM => ['dimension'],
            default => []
        };

        $search = trim($this->filter_str ?? '', '%');
        if (!in_array($field, $fields, true) || $search === '') {
            return $html;
        }

        //highlight text only, never inside HTML tags
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
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
     * Default isset
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, $this->objectslist_fields)) {
            return true;
        }

        return parent::__isset($name);
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
            if (in_array($name, $this->objectslist_fields)) {
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
                '[ObjectsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'selected':
                    if (is_array($value)) {
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[ObjectsList] Value for property `' . $name
                            . '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'filter_str':
                    $this->$name = $value;
                    break;
                case 'category_filter':
                    if (is_numeric($value)) {
                        $this->$name = (int)$value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[ObjectsList] Value for property `' . $name
                            . '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    } else {
                        $this->$name = null;
                    }
                    break;
                case 'active_filter':
                    switch ($value) {
                        case Objects::ALL_OBJECTS:
                        case Objects::ACTIVE_OBJECTS:
                        case Objects::INACTIVE_OBJECTS:
                            $this->active_filter = (int)$value;
                            break;
                        default:
                            Analog::log(
                                '[ObjectsList] Value for active filter should be either '
                                . Objects::ACTIVE_OBJECTS . ', ' . Objects::ACTIVE_OBJECTS . ' or '
                                . Objects::INACTIVE_OBJECTS . ' (' . $value . ' given)',
                                Analog::WARNING
                            );
                            break;
                    }
                    break;
                case 'field_filter':
                    if (is_numeric($value)) {
                        $this->$name = (int)$value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[ObjectsList] Value for property `' . $name
                            . '` should be an integer (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
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
     * Set commons filters for templates
     *
     * @param \GaletteObjectsLend\Entity\Preferences $prefs Preferences instance
     * @param Twig                                   $view  Template reference
     */
    public function setViewCommonsFilters(Preferences $prefs, Twig $view): void
    {
        $prefs = $prefs->getPreferences();

        $options = [
            Objects::FILTER_NAME    => ($prefs['VIEW_DESCRIPTION']
                ? _T("Name/description", "objectslend") : _T("Name", "objectslend")),
            Objects::FILTER_SERIAL  => _T("Serial number", "objectslend"),
            Objects::FILTER_ID      => _T("Id", "objectslend")
        ];

        if ($prefs['VIEW_DIMENSION']) {
            $options[Objects::FILTER_DIM] = _T("Dimensions", "objectslend");
        }

        $view->getEnvironment()->addGlobal('field_filter_options', $options);
    }
}
