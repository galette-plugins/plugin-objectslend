<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\IO\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Object card PDF tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PdfObject extends GaletteTestCase
{
    protected int $seed = 20260924190312;
    protected bool $load_plugins = true;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendObject::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test images of the description are not loaded
     */
    public function testDescriptionImages(): void
    {
        $lendsprefs = new \GaletteObjectsLend\LendPreferences($this->preferences);
        $this->assertTrue($lendsprefs->isEnabled(\GaletteObjectsLend\LendPreferences::VIEW_DESCRIPTION));

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('An object');
        $object->setDescription(
            '<p>A <strong>nice</strong> object</p><img src="' . realpath(GALETTE_ROOT . '../tests/fake_image.jpg') . '" alt=""/>'
        );
        $object->setActive(true);
        $object->store();
        //no picture of its own: any image in the card would come from the description
        $this->assertFalse($object->getPicture()->hasPicture());

        $pdf = new \GaletteObjectsLend\IO\PdfObject($this->zdb, $this->preferences, $lendsprefs);
        $pdf->drawCards([$object]);
        $output = $pdf->Output('card.pdf', 'S');

        $this->assertSame('%PDF', substr($output, 0, 4));
        $this->assertSame(0, substr_count($output, '/Subtype /Image'));
    }
}
