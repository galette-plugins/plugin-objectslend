<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Repository\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Categories tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Categories extends GaletteTestCase
{
    protected int $seed = 20240525091538;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendObject::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendCategory::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test getList
     */
    public function testGetList(): void
    {
        $categories = new \GaletteObjectsLend\Repository\Categories($this->zdb, $this->login);

        $rs_list = $categories->getList();
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $rs_list);
        $this->assertSame(0, $rs_list->count());
        $this->assertSame([], $categories->getList(true));
        $this->assertNull($categories->getCount());

        $this->assertSame([], $categories->getCategoriesList(true));
        $this->assertSame(0, $categories->getCount());

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $category->setName('One category');
        $category->setActive(true);
        $this->assertTrue($category->store());
        $cat_one_id = $category->getId();

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $category->setName('Another category');
        $category->setActive(true);
        $this->assertTrue($category->store());

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $category->setName('Yet another category');
        $category->setActive(false);
        $this->assertTrue($category->store());

        $filters = new \GaletteObjectsLend\Filters\CategoriesList();
        $categories = new \GaletteObjectsLend\Repository\Categories($this->zdb, $this->login, $filters);

        $this->assertCount(3, $categories->getCategoriesList(true));
        $this->assertSame(3, $categories->getCount());

        $filters->filter_str = 'category';
        $this->assertCount(3, $categories->getCategoriesList(true));

        $filters->filter_str = 'Yet';
        $this->assertCount(1, $categories->getCategoriesList(true));

        $filters->filter_str = 'noone';
        $this->assertCount(0, $categories->getCategoriesList(true));

        $filters->reinit();
        $filters->not_empty = true;
        $this->assertCount(0, $categories->getCategoriesList(true));

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('One object');
        $object->setCategoryId($cat_one_id);
        $this->assertTrue($object->store());

        $this->assertCount(1, $categories->getCategoriesList(true));

        $filters->reinit();
        $filters->active_filter = \GaletteObjectsLend\Repository\Categories::ALL_CATEGORIES;
        $this->assertCount(3, $categories->getCategoriesList(true));

        $filters->active_filter = \GaletteObjectsLend\Repository\Categories::ACTIVE_CATEGORIES;
        $this->assertCount(2, $categories->getCategoriesList(true));

        $filters->active_filter = \GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES;
        $this->assertCount(1, $categories->getCategoriesList(true));

        //categories are counted once, whatever the number of their objects
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('Another object');
        $object->setCategoryId($cat_one_id);
        $this->assertTrue($object->store());

        $filters->reinit();
        $this->assertCount(3, $categories->getCategoriesList(true));
        $this->assertSame(3, $categories->getCount());
    }
}
