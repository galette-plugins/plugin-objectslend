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
class Status extends GaletteTestCase
{
    protected int $seed = 20240526084251;

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
     * Test getList
     */
    public function testGetList(): void
    {
        $status = new \GaletteObjectsLend\Repository\Status($this->zdb, $this->login);

        $rs_list = $status->getList();
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $rs_list);
        $this->assertSame(0, $rs_list->count());
        $this->assertSame([], $status->getList(true));
        $this->assertNull($status->getCount());

        $this->assertSame([], $status->getStatusList(true));
        $this->assertSame(0, $status->getCount());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One active in stock status';
        $status->in_stock = true;
        $status->is_active = true;
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One active in stock status';
        $status->in_stock = true;
        $status->is_active = true;
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One active not in stock status';
        $status->in_stock = false;
        $status->is_active = true;
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One inactive in stock status';
        $status->in_stock = true;
        $status->is_active = false;
        $this->assertTrue($status->store());

        $status = new \GaletteObjectsLend\Entity\LendStatus($this->zdb);
        $status->status_text = 'One inactive not in stock status';
        $status->in_stock = false;
        $status->is_active = false;
        $this->assertTrue($status->store());

        $filters = new \GaletteObjectsLend\Filters\StatusList();
        $status = new \GaletteObjectsLend\Repository\Status($this->zdb, $this->login, $filters);

        $this->assertCount(5, $status->getStatusList(true));
        $this->assertSame(5, $status->getCount());

        $filters->filter_str = 'one';
        $this->assertCount(5, $status->getStatusList(true));

        $filters->filter_str = 'not';
        $this->assertCount(2, $status->getStatusList(true));

        $filters->filter_str = 'noone';
        $this->assertCount(0, $status->getStatusList(true));

        $filters->reinit();
        $filters->active_filter = \GaletteObjectsLend\Repository\Status::ACTIVE;
        $this->assertCount(3, $status->getStatusList(true));

        $filters->active_filter = \GaletteObjectsLend\Repository\Status::INACTIVE;
        $this->assertCount(2, $status->getStatusList(true));

        $filters->reinit();
        $filters->stock_filter = \GaletteObjectsLend\Repository\Status::DC_STOCK;
        $this->assertCount(5, $status->getStatusList(true));

        $filters->stock_filter = \GaletteObjectsLend\Repository\Status::IN_STOCK;
        $this->assertCount(3, $status->getStatusList(true));

        $filters->stock_filter = \GaletteObjectsLend\Repository\Status::OUT_STOCK;
        $this->assertCount(2, $status->getStatusList(true));

        //quick test ordering
        $filters->reinit();

        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_ID;
        $this->assertCount(5, $status->getStatusList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_NAME;
        $this->assertCount(5, $status->getStatusList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_ACTIVE;
        $this->assertCount(5, $status->getStatusList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_STOCK;
        $this->assertCount(5, $status->getStatusList(true));

        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_RENTDAYS;
        $this->assertCount(5, $status->getStatusList(true));
    }
}
