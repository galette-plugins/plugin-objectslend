<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\tests\units;

use Galette\Core\PreferencesSchema;
use Galette\Tests\GaletteTestCase;

/**
 * Plugin preferences tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendPreferences extends GaletteTestCase
{
    protected int $seed = 20240519131740;
    protected bool $load_plugins = true;

    /**
     * Preferences are declared to core, with their defaults
     */
    public function testDefaults(): void
    {
        $schema = \GaletteObjectsLend\LendPreferences::getSchema();
        $this->assertCount(17, $schema);
        foreach (array_keys($schema) as $name) {
            $this->assertStringStartsWith(\GaletteObjectsLend\LendPreferences::PREFIX, $name);
            $this->assertSame('objectslend', PreferencesSchema::getOwner($name));
        }

        $prefs = new \GaletteObjectsLend\LendPreferences($this->preferences);
        $this->assertSame(128, $prefs->getThumbWidth());
        $this->assertSame(128, $prefs->getThumbHeight());
        $this->assertTrue($prefs->imagesInLists());
        $this->assertTrue($prefs->isEnabled(\GaletteObjectsLend\LendPreferences::ENABLE_MEMBER_RENT_OBJECT));
        $this->assertFalse($prefs->isEnabled(\GaletteObjectsLend\LendPreferences::VIEW_SERIAL));
        $this->assertSame(5, $prefs->getContributionTypeId());
        $this->assertSame('Location de {NAME} {DESCRIPTION} {SERIAL_NUMBER}', $prefs->getContributionText());

        $values = $prefs->toArray();
        $this->assertCount(17, $values);
        $this->assertSame(128, $values['thumb_max_width']);
        $this->assertFalse($values['view_serial']);
    }

    /**
     * Values are read from core preferences
     */
    public function testChange(): void
    {
        $prefs = new \GaletteObjectsLend\LendPreferences($this->preferences);
        $this->assertTrue(
            $this->preferences->setValue(\GaletteObjectsLend\LendPreferences::THUMB_MAX_WIDTH, 256, $this->login)
        );
        $this->assertTrue(
            $this->preferences->setValue(\GaletteObjectsLend\LendPreferences::VIEW_SERIAL, 1, $this->login)
        );
        $this->assertSame(256, $prefs->getThumbWidth());
        $this->assertTrue($prefs->isEnabled(\GaletteObjectsLend\LendPreferences::VIEW_SERIAL));

        $this->assertFalse(
            $this->preferences->setValue(\GaletteObjectsLend\LendPreferences::THUMB_MAX_HEIGHT, -3, $this->login)
        );
        //an invalid value is not stored
        $this->preferences->load();
        $this->assertSame(128, $prefs->getThumbHeight());
    }

    /**
     * Pictures follow core images size unless the plugin sets its own
     */
    public function testUploadSize(): void
    {
        $prefs = new \GaletteObjectsLend\LendPreferences($this->preferences);
        $this->assertSame(\Galette\IO\File::MAX_FILE_SIZE, $prefs->getUploadSize());

        $this->assertTrue($this->preferences->setValue('pref_upload_size_images', 4096, $this->login));
        $this->assertSame(4096, $prefs->getUploadSize());

        $this->assertTrue(
            $this->preferences->setValue(\GaletteObjectsLend\LendPreferences::UPLOAD_SIZE_IMAGES, 512, $this->login)
        );
        $this->assertSame(512, $prefs->getUploadSize());

        $picture = (new \GaletteObjectsLend\Entity\ObjectPicture())->setMaxLength($prefs->getUploadSize());
        $this->assertSame(512, $picture->getMaxLength());
    }
}
