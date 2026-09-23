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
 * Category tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendCategory extends GaletteTestCase
{
    protected int $seed = 20240521212536;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendCategory::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $this->assertSame('No category (0)', $category->getName());
        $this->assertSame('No category', $category->getName(false));
        $this->assertInstanceOf(\GaletteObjectsLend\Entity\CategoryPicture::class, $category->getPicture());
        $this->assertSame(0.0, $category->getSum());
        $this->assertSame(0, $category->getObjectsNb());
        $this->assertTrue($category->isActive());
        $this->assertNull($category->getId());

        $category = new \GaletteObjectsLend\Entity\LendCategory(
            $this->zdb,
            null,
            ['picture' => false]
        );
        $this->assertNull($category->getPicture());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $category->setName('Test category');
        $category->setActive(false);

        $this->assertTrue($category->store());
        $cid = $category->getId();
        $this->assertGreaterThan(0, $cid);

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb, $cid);
        $this->assertSame('Test category (0)', $category->getName());
        $this->assertFalse($category->isActive());

        $category->setName('Test category (edited)');
        $this->assertTrue($category->store());

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb, $cid);
        $this->assertSame('Test category (edited) (0)', $category->getName());

        $this->assertTrue($category->delete());
        new \GaletteObjectsLend\Entity\LendCategory($this->zdb, $cid);
    }
}
