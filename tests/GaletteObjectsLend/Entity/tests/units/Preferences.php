<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Preferences tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Preferences extends GaletteTestCase
{
    protected int $seed = 20240519131740;

    /**
     * Test defaults
     */
    public function testDefaults(): void
    {
        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
        $this->assertSame(128, $prefs->getThumbWidth());
        $this->assertSame(128, $prefs->getThumbHeight());
        $this->assertTrue($prefs->imagesInLists());
        $this->assertTrue($prefs->showFullsize());

        $all_prefs = $prefs->getPreferences();
        $this->assertCount(17, $all_prefs);
        foreach ($all_prefs as $code => $value) {
            $this->assertSame($value, $prefs->$code);
        }

        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb, false);
        $this->assertTrue($prefs->load());

        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb, false);
        $this->assertCount(17, $prefs->getPreferences());

        $this->expectException(\RuntimeException::class);
        $this->assertSame(null, $prefs->NON_EXISTING);
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
        $orig_prefs = $prefs->getPreferences();
        $this->assertCount(17, $orig_prefs);

        $all_prefs = $orig_prefs;

        $this->assertSame(128, (int)$all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_WIDTH]);
        $this->assertSame(128, (int)$all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_HEIGHT]);
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_WIDTH] = 256;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_HEIGHT] = 256;

        $this->assertTrue($prefs->store($all_prefs));
        $all_prefs = $prefs->getPreferences();
        $this->assertCount(17, $all_prefs);
        $this->assertSame(256, (int)$all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_WIDTH]);
        $this->assertSame(256, (int)$all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_HEIGHT]);

        $this->assertTrue($prefs->store($orig_prefs));
    }
}
