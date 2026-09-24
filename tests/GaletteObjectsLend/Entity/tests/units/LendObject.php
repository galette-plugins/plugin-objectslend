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
    protected bool $load_plugins = true;

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
        $this->assertTrue($object->isActive());
        $this->assertTrue($object->isObjectActive());
        $this->assertNull($object->getCategoryName());
        $this->assertSame('', $object->getMemberName());
        $this->assertSame('', $object->getDescription());
        $this->assertSame('', $object->getDimension());
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
        $objects = new \GaletteObjectsLend\Repository\Objects(
            $this->zdb,
            $this->preferences,
            $this->login,
            new \GaletteObjectsLend\LendPreferences($this->preferences)
        );
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);

        $object->setName('An object');
        $object->setCategoryId($this->active_category_id);
        $object->setActive(true);
        $object->setPrice(1500.00);
        $object->setRentPrice(10.00);
        $object->setPricePerDay(true);
        $object->setWeight(186.00);

        $object->store();
        $oid = $object->getId();
        $this->assertGreaterThan(0, $oid);

        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb);
        $bdate = new \DateTime('2024-05-22 19:46:21');
        $rent->setDateBegin($bdate->format('Y-m-d H:i:s'));
        $edate = clone $bdate;
        $edate->add(new \DateInterval('P1Y'));
        $rent->setDateEnd($edate->format('Y-m-d H:i:s'));
        $rent->setStatusId($this->active_instock_status);
        $rent->setObjectId($oid);
        $rent->store();
        //current rent is set by LendService
        $update = $this->zdb->update(LEND_PREFIX . \GaletteObjectsLend\Entity\LendObject::TABLE)
            ->set([\GaletteObjectsLend\Entity\LendRent::PK => $rent->getId()])
            ->where([\GaletteObjectsLend\Entity\LendObject::PK => $oid]);
        $this->zdb->execute($update);

        $object = $objects->getWithCurrentRent($oid);
        $this->assertTrue($object->isActive());
        $this->assertSame(1500.00, $object->getPrice());
        $this->assertSame(10.00, $object->getRentPrice());
        $this->assertSame(186.00, $object->getWeight());
        $this->assertSame('Active test category', $object->getCategoryName());
        $this->assertSame('One active status', $object->getStatusText());
        $this->assertTrue($object->inStock());
        $this->assertSame('2024-05-22', $object->getDateBegin());

        //loaded alone, without its current rent
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid);
        $this->assertSame($rent->getId(), $object->getRentId());
        $this->assertSame('', $object->getStatusText());
        $this->assertSame('', $object->getDateBegin());
        $object->setName('An object (edited)');
        $object->setDescription('An object description');
        $object->setSerialNumber('SE-aBc-RI@L');
        $object->setDimension('10x50');
        $object->store();

        $object = $objects->getWithCurrentRent($oid);
        $this->assertSame('An object (edited)', $object->getName());
        $this->assertSame('An object description', $object->getDescription());
        $this->assertSame('SE-aBc-RI@L', $object->getSerialNumber());
        $this->assertSame('10x50', $object->getDimension());

        //edit category to inactive one
        $object->setCategoryId($this->inactive_category_id);
        $object->store();
        $object = $objects->getWithCurrentRent($oid);
        $this->assertFalse($object->isActive());
        $this->assertTrue($object->isObjectActive());
        $this->assertSame('Inactive test category', $object->getCategoryName());

        //removing category
        $rm_category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $rm_category->setName('Category to be removed');
        $rm_category->setActive(true);
        $rm_category->store();
        $category_id = $rm_category->getId();

        $object->setCategoryId($category_id);
        $object->store();

        $object = $objects->getWithCurrentRent($oid);
        $this->assertSame($category_id, $object->getCategoryId());

        $rm_category->delete();
        $object = $objects->getWithCurrentRent($oid);
        $this->assertNull($object->getCategoryId());

        //clone
        $object->clone();
        $clone_id = $object->getId();
        $this->assertNotEquals($oid, $clone_id);
        $this->assertSame('An object (edited)', $object->getName());

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $oid);
        $object->delete();
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb, $clone_id);
        $object->delete();
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
        $status->store();
        $this->active_instock_status = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('Another active status');
        $status->setInStock(false);
        $status->setActive(true);
        $status->store();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One inactive status');
        $status->setInStock(true);
        $status->setActive(false);
        $status->store();
    }

    /**
     * Create few categories
     */
    private function createCategories(): void
    {
        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $category->setName('Active test category');
        $category->setActive(true);

        $category->store();
        $this->active_category_id = $category->getId();
        $this->assertGreaterThan(0, $this->active_category_id);

        $category = new \GaletteObjectsLend\Entity\LendCategory($this->zdb);

        $category->setName('Inactive test category');
        $category->setActive(false);

        $category->store();
        $this->inactive_category_id = $category->getId();
        $this->assertGreaterThan(0, $this->inactive_category_id);
    }

    /**
     * Test description is sanitized
     */
    public function testDescriptionHtml(): void
    {
        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setDescription('A & B');
        $this->assertSame('A &amp; B', $object->getDescriptionHtml());

        $description = '<p>Nice <strong>object</strong></p><script>alert("description")</script>';
        $object->setDescription($description);
        $this->assertSame($description, $object->getDescription());
        $this->assertSame('<p>Nice <strong>object</strong></p>', $object->getDescriptionHtml());
    }
}
