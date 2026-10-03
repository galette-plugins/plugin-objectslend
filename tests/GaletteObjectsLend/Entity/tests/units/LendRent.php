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
class LendRent extends GaletteTestCase
{
    protected int $seed = 20240524220704;

    private int $active_instock_status;
    private int $active_notinstock_status;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->createStatus();
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendRent::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendCategory::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendStatus::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb);
        $this->assertNull($rent->getId());
        $this->assertNull($rent->getObjectId());
        $this->assertNull($rent->getStatusId());
        $this->assertNull($rent->getAdherentId());
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $rent->getDateBegin());
        $this->assertSame('', $rent->getDateForecast());
        $this->assertSame('', $rent->getDateEnd());
        $this->assertSame('', $rent->getComments());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb);

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->setName('Test object');
        $object->store();
        $oid = $object->getId();

        $bdate = new \DateTime('2024-05-22 19:46:21');
        $rent->setDateBegin($bdate->format('Y-m-d H:i:s'));
        $rent->setObjectId($oid);
        $rent->setStatusId($this->active_instock_status);
        $rent->setComments('Test comment');
        $rent->store();
        $rent_id = $rent->getId();

        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb, $rent_id);
        $this->assertSame($this->active_instock_status, $rent->getStatusId());

        //update rent
        $member = $this->getMemberOne();
        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb, $rent_id);
        $rent->setStatusId($this->active_notinstock_status);
        $rent->setAdherentId($member->id);
        $rent->store();

        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb, $rent_id);
        $this->assertSame($this->active_notinstock_status, $rent->getStatusId());
        $this->assertSame($member->id, $rent->getAdherentId());

        //another (older) rent
        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb);
        $bdate = new \DateTime('2024-05-22 19:46:21');
        $bdate->sub(new \DateInterval('P2Y'));
        $rent->setDateBegin($bdate->format('Y-m-d H:i:s'));
        $rent2_edate = clone $bdate;
        $rent2_edate->add(new \DateInterval('P1Y'));
        $rent->setDateEnd($rent2_edate->format('Y-m-d H:i:s'));
        $rent->setDateForecast($rent2_edate->format('Y-m-d'));
        $rent->setObjectId($oid);
        $rent->setStatusId($this->active_instock_status);
        $rent->setComments('Test 2 comment');
        $rent->store();
        $rent2_id = $rent->getId();
        $this->assertNotEquals($rent_id, $rent2_id);
        $this->assertSame($rent2_edate->format('Y-m-d'), $rent->getDateForecast());

        $object_rents = (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($oid);
        $this->assertCount(2, $object_rents);

        //only last
        $object_rents = (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($oid, true);
        $this->assertCount(1, $object_rents);

        //close all rents
        (new \GaletteObjectsLend\Repository\Rents($this->zdb))->closeAllForObject($oid, 'Now closed.');

        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb, $rent_id);
        $this->assertSame('Now closed.', $rent->getComments());

        //had an end_date, should not be modified
        $rent = new \GaletteObjectsLend\Entity\LendRent($this->zdb, $rent2_id);
        $this->assertEquals($rent2_edate->format('Y-m-d H:i'), $rent->getDateEnd());
        $this->assertSame('Test 2 comment', $rent->getComments());

        //cleanup to avoid constraint errors
        $object->delete();

        /*$status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One active status');
        $status->setInStock(true);
        $status->setActive(true);
        $this->assertTrue($status->store());
        $status_one = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('Another active status');
        $status->setInStock(false);
        $status->setActive(true);
        $this->assertTrue($status->store());
        $status_two = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One inactive status');
        $status->setInStock(true);
        $status->setActive(false);
        $this->assertTrue($status->store());
        $status_three = $status->getId();

        $list = (new \GaletteObjectsLend\Repository\Status($this->zdb, $this->preferences, $this->login))->getActiveTakeAwayStatuses();
        $this->assertCount(1, $list);

        $active_one = $list[0];
        $this->assertSame($status_two, $active_one->getId());
        $this->assertSame('Another active status', $active_one->getText());

        $list = (new \GaletteObjectsLend\Repository\Status($this->zdb, $this->preferences, $this->login))->getActiveStockStatuses();
        $this->assertCount(1, $list);

        $active_one = $list[0];
        $this->assertSame($status_one, $active_one->getId());
        $this->assertSame('One active status', $active_one->getText());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $status->setText('One active status (edited)');
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertSame('One active status (edited)', $status->getText());

        $this->assertTrue($status->delete());
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertNull($status->getId());*/
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
        $this->active_notinstock_status = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One inactive status');
        $status->setInStock(true);
        $status->setActive(false);
        $status->store();
    }
}
