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
        $this->assertSame(null, $prefs->NON_EXISTING); // @phpstan-ignore property.notFound
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

    /**
     * Stored values are read back from the database
     */
    public function testReload(): void
    {
        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
        $orig_prefs = $prefs->getPreferences();

        $all_prefs = $orig_prefs;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT] = 0;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_AUTO_GENERATE_CONTRIBUTION] = 1;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIBUTION_TYPE_ID] = 3;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT] = 'Rent of {NAME}';
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_THUMB_MAX_WIDTH] = 200;
        $this->assertTrue($prefs->store($all_prefs));

        try {
            $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
            $this->assertFalse((bool)$prefs->{\GaletteObjectsLend\Entity\Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT});
            $this->assertTrue((bool)$prefs->{\GaletteObjectsLend\Entity\Preferences::PARAM_AUTO_GENERATE_CONTRIBUTION});
            $this->assertSame(
                3,
                (int)$prefs->{\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIBUTION_TYPE_ID}
            );
            $this->assertSame(
                'Rent of {NAME}',
                $prefs->{\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT}
            );
            $this->assertSame(200, $prefs->getThumbWidth());
            $this->assertSame(128, $prefs->getThumbHeight());
            $this->assertCount(17, $prefs->getPreferences());
        } finally {
            $this->assertTrue($prefs->store($orig_prefs));
        }

        $prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
        $this->assertSame(128, $prefs->getThumbWidth());
        $this->assertSame(
            (string)$orig_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT],
            (string)$prefs->{\GaletteObjectsLend\Entity\Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT}
        );
    }
}
