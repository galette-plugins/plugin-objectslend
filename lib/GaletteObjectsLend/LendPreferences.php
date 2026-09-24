<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend;

use Galette\Core\Preferences;
use Galette\Core\PreferencesSchema;

/**
 * Plugin preferences
 *
 * They are stored by core preferences, which this class declares them to and
 * reads them from. Names are the former `lend_parameters` codes, lowercased
 * and prefixed; the upgrade script relies on it.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class LendPreferences
{
    public const string PREFIX = 'pref_objectslend_';

    /** Allow non staff members to rent objects */
    public const string ENABLE_MEMBER_RENT_OBJECT = self::PREFIX . 'enable_member_rent_object';
    /** Generate automatically a contribution when an object is rented */
    public const string AUTO_GENERATE_CONTRIBUTION = self::PREFIX . 'auto_generate_contribution';
    /** Type of the generated contribution */
    public const string GENERATED_CONTRIBUTION_TYPE_ID = self::PREFIX . 'generated_contribution_type_id';
    /** Information text of the generated contribution, with placeholders */
    public const string GENERATED_CONTRIB_INFO_TEXT = self::PREFIX . 'generated_contrib_info_text';
    /** Maximum width of a thumbnail, in pixels */
    public const string THUMB_MAX_WIDTH = self::PREFIX . 'thumb_max_width';
    /** Maximum height of a thumbnail, in pixels */
    public const string THUMB_MAX_HEIGHT = self::PREFIX . 'thumb_max_height';
    /** Show images in objects and categories lists */
    public const string VIEW_THUMBNAIL = self::PREFIX . 'view_thumbnail';
    /** Show categories on the objects list */
    public const string VIEW_CATEGORY = self::PREFIX . 'view_category';
    /** Show the forecast return date */
    public const string VIEW_DATE_FORECAST = self::PREFIX . 'view_date_forecast';
    /** Show the description */
    public const string VIEW_DESCRIPTION = self::PREFIX . 'view_description';
    /** Show dimensions */
    public const string VIEW_DIMENSION = self::PREFIX . 'view_dimension';
    /** Show the rent price */
    public const string VIEW_LEND_PRICE = self::PREFIX . 'view_lend_price';
    /** Show the sum of prices on the objects list */
    public const string VIEW_LIST_PRICE_SUM = self::PREFIX . 'view_list_price_sum';
    /** Show the buy price */
    public const string VIEW_PRICE = self::PREFIX . 'view_price';
    /** Show the serial number */
    public const string VIEW_SERIAL = self::PREFIX . 'view_serial';
    /** Show the weight */
    public const string VIEW_WEIGHT = self::PREFIX . 'view_weight';

    /**
     * Constructor
     *
     * @param Preferences $preferences Core preferences
     */
    public function __construct(private readonly Preferences $preferences)
    {
    }

    /**
     * Get the preferences the plugin declares
     *
     * Defaults are the ones the former install scripts inserted; contribution
     * type 5 is the "donation in money" core installs.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSchema(): array
    {
        $schema = [
            self::GENERATED_CONTRIBUTION_TYPE_ID => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 5,
                'min' => 0,
                'error' => PreferencesSchema::ERR_POSITIVE_NUMBER,
            ],
            self::GENERATED_CONTRIB_INFO_TEXT => [
                'type' => PreferencesSchema::TYPE_STRING,
                'default' => 'Location de {NAME} {DESCRIPTION} {SERIAL_NUMBER}',
            ],
            self::THUMB_MAX_WIDTH => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 128,
                'min' => 1,
                'error' => PreferencesSchema::ERR_POSITIVE_NUMBER,
            ],
            self::THUMB_MAX_HEIGHT => [
                'type' => PreferencesSchema::TYPE_INT,
                'default' => 128,
                'min' => 1,
                'error' => PreferencesSchema::ERR_POSITIVE_NUMBER,
            ],
        ];

        foreach (self::getBooleans() as $name => $default) {
            $schema[$name] = [
                'type' => PreferencesSchema::TYPE_BOOL,
                'default' => $default,
            ];
        }

        return $schema;
    }

    /**
     * Get yes/no preferences, with their default value
     *
     * A form does not post an unchecked box: this is the list to tell it
     * from a value that was not part of the form at all.
     *
     * @return array<string, bool>
     */
    public static function getBooleans(): array
    {
        return [
            self::ENABLE_MEMBER_RENT_OBJECT => true,
            self::AUTO_GENERATE_CONTRIBUTION => true,
            self::VIEW_THUMBNAIL => true,
            self::VIEW_CATEGORY => false,
            self::VIEW_DATE_FORECAST => true,
            self::VIEW_DESCRIPTION => true,
            self::VIEW_DIMENSION => false,
            self::VIEW_LEND_PRICE => false,
            self::VIEW_LIST_PRICE_SUM => false,
            self::VIEW_PRICE => false,
            self::VIEW_SERIAL => false,
            self::VIEW_WEIGHT => false,
        ];
    }

    /**
     * Is a yes/no preference on?
     *
     * @param string $name Preference name, one of the class constants
     */
    public function isEnabled(string $name): bool
    {
        return (bool)$this->preferences->getPluginValue($name);
    }

    /**
     * Get type of the generated contribution
     */
    public function getContributionTypeId(): int
    {
        return (int)$this->preferences->getPluginValue(self::GENERATED_CONTRIBUTION_TYPE_ID);
    }

    /**
     * Get information text of the generated contribution
     */
    public function getContributionText(): string
    {
        return (string)$this->preferences->getPluginValue(self::GENERATED_CONTRIB_INFO_TEXT);
    }

    /**
     * Get thumbnail maximum width
     */
    public function getThumbWidth(): int
    {
        return (int)$this->preferences->getPluginValue(self::THUMB_MAX_WIDTH);
    }

    /**
     * Get thumbnail maximum height
     */
    public function getThumbHeight(): int
    {
        return (int)$this->preferences->getPluginValue(self::THUMB_MAX_HEIGHT);
    }

    /**
     * Whether to display images (as thumbnails) in lists
     */
    public function imagesInLists(): bool
    {
        return $this->isEnabled(self::VIEW_THUMBNAIL);
    }

    /**
     * Get every preference, named without its prefix, for templates
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        $values = [];
        foreach (self::getSchema() as $name => $entry) {
            //core hands a false boolean back as an empty string
            $values[substr($name, strlen(self::PREFIX))] = match ($entry['type']) {
                PreferencesSchema::TYPE_BOOL => $this->isEnabled($name),
                PreferencesSchema::TYPE_INT => (int)$this->preferences->getPluginValue($name),
                default => (string)$this->preferences->getPluginValue($name),
            };
        }
        return $values;
    }
}
