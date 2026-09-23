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
 * @property ?string    $filter_str
 * @property ?int       $category_filter
 * @property ?int       $active_filter
 * @property ?int       $field_filter
 * @property array<int> $selected
 */
class ObjectsList extends Pagination
{
    use ListFilters;

    private ?int $category_filter = null;
    private ?int $field_filter = null;
    /** @var array<int> */
    private array $selected = [];

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
        $this->reinitListFilters();
        $this->category_filter = null;
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
     * Filtering properties of the class, besides filter_str and active_filter
     *
     * @return array<string>
     */
    protected function getOwnFilters(): array
    {
        return ['category_filter', 'field_filter', 'selected'];
    }

    /**
     * Set a filtering property of the class
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     */
    protected function setOwnFilter(string $name, mixed $value): bool
    {
        switch ($name) {
            case 'selected':
                if (is_array($value)) {
                    $this->selected = array_map('intval', $value);
                } elseif ($value !== null) {
                    $this->warnType($name, 'an array', $value);
                }
                return true;
            case 'category_filter':
            case 'field_filter':
                if (is_numeric($value)) {
                    $this->$name = (int)$value;
                } elseif ($value === null) {
                    $this->$name = null;
                } else {
                    $this->warnType($name, 'an integer', $value);
                }
                return true;
        }
        return false;
    }

    /**
     * Log a value of wrong type
     *
     * @param string $name     Property name
     * @param string $expected Expected type
     * @param mixed  $value    Value
     */
    private function warnType(string $name, string $expected, mixed $value): void
    {
        Analog::log(
            sprintf(
                '[%1$s] Value for %2$s should be %3$s (%4$s given)',
                static::class,
                $name,
                $expected,
                gettype($value)
            ),
            Analog::WARNING
        );
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
