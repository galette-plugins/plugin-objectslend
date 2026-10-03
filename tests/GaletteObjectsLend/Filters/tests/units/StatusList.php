<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;

/**
 * Status filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StatusList extends GaletteTestCase
{
    /**
     * Test filter defaults values
     *
     * @param \GaletteObjectsLend\Filters\StatusList $filters Filters instance
     */
    protected function testDefaults(\GaletteObjectsLend\Filters\StatusList $filters): void
    {
        $this->assertSame(\GaletteObjectsLend\Repository\Status::ORDERBY_NAME, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());
        $this->assertNull($filters->filter_str);
        $this->assertNull($filters->active_filter);
        $this->assertNull($filters->stock_filter);
    }

    /**
     * Test creation
     */
    public function testCreate(): void
    {
        $filters = new \GaletteObjectsLend\Filters\StatusList();

        $this->testDefaults($filters);

        //change order field
        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_STOCK;
        $this->assertSame(\GaletteObjectsLend\Repository\Status::ORDERBY_STOCK, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //same order field again: direction inverted
        $filters->orderby = \GaletteObjectsLend\Repository\Status::ORDERBY_STOCK;
        $this->assertSame(\GaletteObjectsLend\Repository\Status::ORDERBY_STOCK, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //not existing order, same kept
        $filters->setDirection('abcd');
        $this->expectLogEntry(
            Analog::WARNING,
            '[GaletteObjectsLend\Filters\StatusList|Pagination] "abcd" is not a valid backing value for enum Galette\Enums\SQLOrder'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Status::ORDERBY_STOCK, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //change direction only
        $filters->setDirection(\Galette\Enums\SQLOrder::ASC);
        $this->assertSame(\GaletteObjectsLend\Repository\Status::ORDERBY_STOCK, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //set string filter
        $filters->filter_str = 'a string';
        $this->assertSame('a string', $filters->filter_str);

        //Set activity filter
        $filters->active_filter = \GaletteObjectsLend\Repository\Status::INACTIVE;
        $this->assertSame(\GaletteObjectsLend\Repository\Status::INACTIVE, $filters->active_filter);

        //out of known values, no change
        $filters->active_filter = 42;
        $this->expectLogEntry(
            Analog::WARNING,
            '[GaletteObjectsLend\Filters\StatusList] Value for active_filter should be one of 0, 1, 2 (42 given)'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Status::INACTIVE, $filters->active_filter);

        //set stock filter
        $filters->stock_filter = \GaletteObjectsLend\Repository\Status::IN_STOCK;
        $this->assertSame(\GaletteObjectsLend\Repository\Status::IN_STOCK, $filters->stock_filter);

        $filters->stock_filter = \GaletteObjectsLend\Repository\Status::OUT_STOCK;
        $this->assertSame(\GaletteObjectsLend\Repository\Status::OUT_STOCK, $filters->stock_filter);

        //out of known values, no change
        $filters->stock_filter = 42;
        $this->expectLogEntry(
            Analog::WARNING,
            '[GaletteObjectsLend\Filters\StatusList] Value for stock_filter should be one of 0, 1, 2 (42 given)'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Status::OUT_STOCK, $filters->stock_filter);

        //reinit and test defaults are back
        $filters->reinit();
        $this->testDefaults($filters);
    }

    /**
     * Test setting non existing filter
     */
    public function testSetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\StatusList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $filters->non_existing = 42; // @phpstan-ignore property.notFound
    }

    /**
     * Test getting non existing filter
     */
    public function testGetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\StatusList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $this->assertNull($filters->non_existing); // @phpstan-ignore property.notFound
    }
}
