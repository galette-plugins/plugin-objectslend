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
class Objects extends GaletteTestCase
{
    protected int $seed = 20240526224135;

    protected \GaletteObjectsLend\Entity\Preferences $lend_prefs;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->lend_prefs = new \GaletteObjectsLend\Entity\Preferences($this->zdb);
    }

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
        $objects = new \GaletteObjectsLend\Repository\Objects($this->zdb, $this->preferences, $this->login, $this->lend_prefs);

        $rs_list = $objects->getList();
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $rs_list);
        $this->assertSame(0, $rs_list->count());
        $this->assertSame([], $objects->getList(true));
        $this->assertNull($objects->getCount());

        $this->assertSame([], $objects->getObjectsList(true));
        $this->assertSame(0, $objects->getCount());

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $category->setName('First category');
        $category->setActive(true);
        $this->assertTrue($category->store());
        $first_category_id = $category->getId();
        $this->assertGreaterThan(0, $first_category_id);

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);
        $category->setName('Second category');
        $category->setActive(true);
        $this->assertTrue($category->store());
        $second_category_id = $category->getId();
        $this->assertGreaterThan(0, $second_category_id);

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('First object');
        $object->setCategoryId($first_category_id);
        $object->setActive(true);
        $this->assertTrue($object->store());
        $first_object_id = $object->getId();

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('Second object');
        $object->setDescription('First description');
        $object->setCategoryId($first_category_id);
        $object->setActive(true);
        $this->assertTrue($object->store());
        $second_object_id = $object->getId();

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('Third object');
        $object->setCategoryId($second_category_id);
        $object->setActive(true);
        $this->assertTrue($object->store());
        $third_object_id = $object->getId();

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('Fourth object');
        $object->setSerialNumber('GGABCDEXX');
        $object->setDimension('210x297');
        $object->setActive(false);
        $this->assertTrue($object->store());
        //ids are not reset between tests, a hardcoded one may exist
        $missing_id = $object->getId() + 1;

        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $objects = new \GaletteObjectsLend\Repository\Objects($this->zdb, $this->preferences, $this->login, $this->lend_prefs, $filters);

        $this->assertCount(4, $objects->getObjectsList(true));
        $this->assertSame(4, $objects->getCount());

        $selected = [
            $first_object_id,
            $third_object_id
        ];
        $filters->selected = $selected;
        $this->assertCount(2, $objects->getObjectsList(true));
        $this->assertSame(2, $objects->getCount());

        $filters->reinit();
        $filters->active_filter = \GaletteObjectsLend\Repository\Objects::ACTIVE_OBJECTS;
        $this->assertCount(3, $objects->getObjectsList(true));
        $this->assertSame(3, $objects->getCount());

        $filters->active_filter = \GaletteObjectsLend\Repository\Objects::INACTIVE_OBJECTS;
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        $filters->reinit();
        $filters->category_filter = -1;
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        $filters->category_filter = $first_category_id;
        $this->assertCount(2, $objects->getObjectsList(true));
        $this->assertSame(2, $objects->getCount());

        $filters->category_filter = $second_category_id;
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        $filters->reinit();
        $filters->filter_str = 'OBJECT';
        $this->assertCount(4, $objects->getObjectsList(true));
        $this->assertSame(4, $objects->getCount());

        $filters->filter_str = 'first';
        $this->assertCount(2, $objects->getObjectsList(true));
        $this->assertSame(2, $objects->getCount());

        //disable view description
        $orig_prefs = $this->lend_prefs->getPreferences();
        $all_prefs = $orig_prefs;
        $all_prefs[\GaletteObjectsLend\Entity\Preferences::PARAM_VIEW_DESCRIPTION] = 0;
        $this->assertTrue($this->lend_prefs->store($all_prefs));

        //only one result (first in name only)
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        //reset prefs
        $this->assertTrue($this->lend_prefs->store($orig_prefs));

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_ID;
        $filters->filter_str = (string)$third_object_id;
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        $filters->filter_str = (string)$missing_id;
        $this->assertCount(0, $objects->getObjectsList(true));
        $this->assertSame(0, $objects->getCount());

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_SERIAL;
        $filters->filter_str = 'ABCDE';
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_DIM;
        $filters->filter_str = '297';
        $this->assertCount(1, $objects->getObjectsList(true));
        $this->assertSame(1, $objects->getCount());

        //quick test ordering
        $filters->reinit();

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_NAME;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_SERIAL;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_PRICE;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_RENTPRICE;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_WEIGHT;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_BDATE;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_FDATE;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_MEMBER;
        $this->assertCount(4, $objects->getObjectsList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_CATEGORY;
        $this->assertCount(4, $objects->getObjectsList(true));

        $this->assertTrue($objects->removeObjects([$first_object_id, $second_object_id, $third_object_id]));
        $this->assertCount(1, $objects->getObjectsList(true));
    }
}
