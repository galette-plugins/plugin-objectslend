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
class LendStatus extends GaletteTestCase
{
    protected int $seed = 20240521230915;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        //remove default statuses inserted by install SQL
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendStatus::TABLE);
        $this->zdb->execute($delete);
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . \GaletteObjectsLend\Entity\LendStatus::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $this->assertNull($status->status_id);
        $this->assertSame('', $status->status_text);
        $this->assertFalse($status->in_stock);
        $this->assertTrue($status->is_active);
        $this->assertNull($status->rent_day_number);
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
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
        $this->assertNull($status->status_id);
    }
}
