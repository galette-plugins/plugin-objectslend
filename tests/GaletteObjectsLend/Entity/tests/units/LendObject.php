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
 * Status tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendObject extends GaletteTestCase
{
    protected int $seed = 20240522000325;

    private int $active_category_id;
    private int $inactive_category_id;
    private int $active_instock_status;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->createCategories();
        $this->createStatus();
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendRent::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendObject::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendCategory::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendStatus::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $this->assertSame('€', $object->getCurrency());
        $this->assertNull($object->getCurrentRent());
        $this->assertTrue($object->isActive());
        $this->assertNull($object->getId());
        $this->assertNull($object->getCategoryId());
        $this->assertSame('', $object->getName());
        $this->assertSame(0.0, $object->getPrice());
        $this->assertSame(0.0, $object->getRentPrice());
        $this->assertFalse($object->isPricePerDay());
        $this->assertSame(0.0, $object->getWeight());
        $this->assertSame('', $object->getStatusText());
        $this->assertTrue($object->inStock());
        $this->assertSame('', $object->getDateBegin());
        $this->assertSame('', $object->getDateForecast());
        $this->assertNull($object->getIdAdh());
        $this->assertNull($object->getRentId());
        $this->assertNull($object->getCategoryId());
        $this->assertSame('', $object->getSerialNumber());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $deps = [
            'category' => true,
            'status' => true,
            'last_rent' => true
        ];
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, null, $deps);

        $object->name = 'An object';
        $object->category_id = $this->active_category_id;
        $object->is_active = true;
        $object->price = 1500.00;
        $object->rent_price = 10.00;
        $object->price_per_day = true;
        $object->weight = 186.00;

        $this->assertTrue($object->store());
        $oid = $object->getId();
        $this->assertGreaterThan(0, $oid);

        $rent = new \GaletteObjectsLend\Entity\LendRent();
        $bdate = new \DateTime('2024-05-22 19:46:21');
        $rent->date_begin = $bdate->format('Y-m-d H:i:s');
        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));
        $rent->date_end = $edate->format('Y-m-d H:i:s');
        $rent->status_id = $this->active_instock_status;
        $rent->object_id = $oid;
        $this->assertTrue($rent->store());
        //current rent is set by LendService
        $update = $this->zdb->update(LEND_PREFIX . \GaletteObjectsLend\Entity\LendObject::TABLE)
            ->set([\GaletteObjectsLend\Entity\LendRent::PK => $rent->rent_id])
            ->where([\GaletteObjectsLend\Entity\LendObject::PK => $oid]);
        $this->zdb->execute($update);

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid, $deps);
        $this->assertTrue($object->isActive());
        $this->assertSame(1500.00, $object->getPrice());
        $this->assertSame('1 500,00', $object->price);
        $this->assertSame(10.00, $object->getRentPrice());
        $this->assertSame('10,00', $object->rent_price);
        $this->assertSame(10.00, $object->value_rent_price);
        $this->assertSame(186.00, $object->getWeight());
        $this->assertSame('186,000', $object->weight);

        $this->assertSame('2024-05-22', $object->getDateBegin());
        $this->assertSame('2024-05-22', $object->date_begin);
        $object->name = 'An object (edited)';
        $object->description = 'An object description';
        $object->serial_number = 'SE-aBc-RI@L';
        $object->dimension = '10x50';
        $this->assertTrue($object->store());

        $filter = new \GaletteObjectsLend\Filters\ObjectsList();
        $filter->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_NAME;
        $filter->filter_str = 'object';
        $this->assertSame('An <span class="search">object</span> (edited)', $object->displayName($filter));
        $this->assertSame('An <span class="search">object</span> description', $object->displayDescription($filter));

        $filter = new \GaletteObjectsLend\Filters\ObjectsList();
        $filter->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_SERIAL;
        $filter->filter_str = 'abc';
        $this->assertSame('SE-<span class="search">aBc</span>-RI@L', $object->displaySerial($filter));

        $filter = new \GaletteObjectsLend\Filters\ObjectsList();
        $filter->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_DIM;
        $filter->filter_str = '50';
        $this->assertSame('10x<span class="search">50</span>', $object->displayDimension($filter));

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid, $deps);
        $this->assertSame('An object (edited)', $object->getName());

        //edit category to inactive one
        $object->category_id = $this->inactive_category_id;
        $this->assertNull($object->member);
        $this->assertNull($object->rents);
        $this->assertTrue($object->store());
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid, $deps + ['member' => true, 'rents' => true]);
        $this->assertFalse($object->isActive());
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $object->member);

        //removing category
        $rm_category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $rm_category->setName('Category to be removed');
        $rm_category->setActive(true);
        $this->assertTrue($rm_category->store());
        $category_id = $rm_category->getId();

        $object->category_id = $category_id;
        $this->assertTrue($object->store());

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid, $deps);
        $this->assertSame($category_id, $object->getCategoryId());

        $this->assertTrue($rm_category->delete());
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid, $deps);
        $this->assertNull($object->getCategoryId());

        //clone
        $this->assertTrue($object->clone());
        $clone_id = $object->getId();
        $this->assertNotEquals($oid, $clone_id);
        $this->assertSame('An object (edited)', $object->getName());

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid);
        $this->assertTrue($object->delete());
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $clone_id);
        $this->assertTrue($object->delete());
    }

    /**
     * Create few status
     */
    private function createStatus(): void
    {
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One active status');
        $status->setInStock(true);
        $status->setActive(true);
        $this->assertTrue($status->store());
        $this->active_instock_status = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('Another active status');
        $status->setInStock(false);
        $status->setActive(true);
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One inactive status');
        $status->setInStock(true);
        $status->setActive(false);
        $this->assertTrue($status->store());
    }

    /**
     * Create few categories
     */
    private function createCategories(): void
    {
        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $category->setName('Active test category');
        $category->setActive(true);

        $this->assertTrue($category->store());
        $this->active_category_id = $category->getId();
        $this->assertGreaterThan(0, $this->active_category_id);

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $category->setName('Inactive test category');
        $category->setActive(false);

        $this->assertTrue($category->store());
        $this->inactive_category_id = $category->getId();
        $this->assertGreaterThan(0, $this->inactive_category_id);
    }

    /**
     * Test search highlighting is escaped
     */
    public function testHighlight(): void
    {
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->name = '<script>alert("name")</script> (test)';
        $object->description = 'A & B';

        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $this->assertSame(
            '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; (test)',
            $object->displayName($filters)
        );
        $this->assertSame('A &amp; B', $object->displayDescription($filters));

        //regexp special chars are not interpreted
        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_NAME;
        $filters->filter_str = '(test';
        $this->assertSame(
            '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; <span class="search">(test</span>)',
            $object->displayName($filters)
        );
        $filters->filter_str = '(test)';
        $this->assertSame(
            '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; <span class="search">(test)</span>',
            $object->displayName($filters)
        );
        //search matches escaped content, and highlighting does not break it
        $filters->filter_str = 'script>';
        $this->assertSame(
            '&lt;<span class="search">script&gt;</span>alert(&quot;name&quot;)&lt;/<span class="search">script&gt;</span> (test)',
            $object->displayName($filters)
        );
        $filters->filter_str = 'a & b';
        $this->assertSame('<span class="search">A &amp; B</span>', $object->displayDescription($filters));

        //description may contain HTML, which is sanitized
        $object->description = '<p>Nice <strong>object</strong></p><script>alert("description")</script>';
        $filters->filter_str = null;
        $this->assertSame('<p>Nice <strong>object</strong></p>', $object->displayDescription($filters));
        $this->assertSame('<p>Nice <strong>object</strong></p>', $object->getDescriptionHtml());
        $filters->filter_str = 'strong';
        $this->assertSame('<p>Nice <strong>object</strong></p>', $object->displayDescription($filters));
        $filters->filter_str = 'object';
        $this->assertSame(
            '<p>Nice <strong><span class="search">object</span></strong></p>',
            $object->displayDescription($filters)
        );
    }
}
