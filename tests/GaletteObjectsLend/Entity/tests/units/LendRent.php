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
        $rent = new \GaletteObjectsLend\Entity\LendRent();
        $this->assertNull($rent->rent_id);
        $this->assertNull($rent->object_id);
        $this->assertNull($rent->status_id);
        $this->assertNull($rent->adherent_id);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $rent->date_begin);
        $this->assertSame('', $rent->date_forecast);
        $this->assertSame('', $rent->date_end);
        $this->assertSame('', $rent->comments);
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $rent = new \GaletteObjectsLend\Entity\LendRent();

        $object = new \GaletteObjectsLend\Entity\LendObject($this->zdb);
        $object->name = 'Test object';
        $this->assertTrue($object->store());
        $oid = $object->object_id;

        $bdate = new \DateTime('2024-05-22 19:46:21');
        $rent->date_begin = $bdate->format('Y-m-d H:i:s');
        $rent->object_id = $oid;
        $rent->status_id = $this->active_instock_status;
        $rent->comments = 'Test comment';
        $this->assertTrue($rent->store());
        $rent_id = $rent->rent_id;

        $rent = new \GaletteObjectsLend\Entity\LendRent($rent_id);
        $this->assertSame($this->active_instock_status, $rent->status_id);

        //update rent
        $member = $this->getMemberOne();
        $rent = new \GaletteObjectsLend\Entity\LendRent($rent_id);
        $rent->status_id = $this->active_notinstock_status;
        $rent->adherent_id = $member->id;
        $this->assertTrue($rent->store());

        $rent = new \GaletteObjectsLend\Entity\LendRent($rent_id);
        $this->assertSame($this->active_notinstock_status, $rent->status_id);
        $this->assertSame($member->id, $rent->adherent_id);

        //another (older) rent
        $rent = new \GaletteObjectsLend\Entity\LendRent();
        $bdate = new \DateTime('2024-05-22 19:46:21');
        $bdate->sub(new \DateInterval('P2Y'));
        $rent->date_begin = $bdate->format('Y-m-d H:i:s');
        $rent2_edate = clone $bdate;
        $rent2_edate->add(new \DateInterval('P1Y'));
        $rent->date_end = $rent2_edate->format('Y-m-d H:i:s');
        $rent->date_forecast = $rent2_edate->format('Y-m-d');
        $rent->object_id = $oid;
        $rent->status_id = $this->active_instock_status;
        $rent->comments = 'Test 2 comment';
        $this->assertTrue($rent->store());
        $rent2_id = $rent->rent_id;
        $this->assertNotEquals($rent_id, $rent2_id);
        $this->assertSame($rent2_edate->format('Y-m-d'), $rent->date_forecast);

        $object_rents = $rent::getRentsForObjectId($oid);
        $this->assertCount(2, $object_rents);

        //only last
        $object_rents = $rent::getRentsForObjectId($oid, true);
        $this->assertCount(1, $object_rents);

        //close all rents
        $this->assertTrue($rent::closeAllRentsForObject($oid, 'Now closed.'));

        $rent = new \GaletteObjectsLend\Entity\LendRent($rent_id);
        $this->assertSame('Now closed.', $rent->comments);

        //had an end_date, should not be modified
        $rent = new \GaletteObjectsLend\Entity\LendRent($rent2_id);
        $this->assertEquals($rent2_edate->format('Y-m-d H:i'), $rent->date_end);
        $this->assertSame('Test 2 comment', $rent->comments);

        //cleanup to avoid constraint errors
        $this->assertTrue($object->delete());

        /*$status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One active status';
        $status->in_stock = true;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $status_one = $status->status_id;

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'Another active status';
        $status->in_stock = false;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $status_two = $status->status_id;

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One inactive status';
        $status->in_stock = true;
        $status->is_active = false;
        $this->assertTrue($status->store());
        $status_three = $status->status_id;

        $list = $status::getActiveTakeAwayStatuses($this->zdb);
        $this->assertCount(1, $list);

        $active_one = $list[0];
        $this->assertSame($status_two, $active_one->status_id);
        $this->assertSame('Another active status', $active_one->status_text);

        $list = $status::getActiveStockStatuses($this->zdb);
        $this->assertCount(1, $list);

        $active_one = $list[0];
        $this->assertSame($status_one, $active_one->status_id);
        $this->assertSame('One active status', $active_one->status_text);

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $status->status_text = 'One active status (edited)';
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertSame('One active status (edited)', $status->status_text);

        $this->assertTrue($status->delete());
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertNull($status->status_id);*/
    }

    /**
     * Create few status
     */
    private function createStatus(): void
    {
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One active status';
        $status->in_stock = true;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $this->active_instock_status = $status->status_id;

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'Another active status';
        $status->in_stock = false;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $this->active_notinstock_status = $status->status_id;

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One inactive status';
        $status->in_stock = true;
        $status->is_active = false;
        $this->assertTrue($status->store());
    }
}
