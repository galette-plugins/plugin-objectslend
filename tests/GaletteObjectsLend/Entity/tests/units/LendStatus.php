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
        $this->assertNull($status->getId());
        $this->assertSame('', $status->getText());
        $this->assertFalse($status->isInStock());
        $this->assertTrue($status->isActive());
        $this->assertNull($status->getRentDayNumber());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One active status');
        $status->setInStock(true);
        $status->setActive(true);
        $status->store();
        $status_one = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('Another active status');
        $status->setInStock(false);
        $status->setActive(true);
        $status->store();
        $status_two = $status->getId();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->setText('One inactive status');
        $status->setInStock(true);
        $status->setActive(false);
        $status->store();

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
        $status->store();

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertSame('One active status (edited)', $status->getText());

        $status->delete();
        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb, $status_one);
        $this->assertNull($status->getId());
    }
}
