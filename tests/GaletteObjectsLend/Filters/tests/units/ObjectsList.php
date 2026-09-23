<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Filters\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Objects filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ObjectsList extends GaletteTestCase
{
    /**
     * Test filter defaults values
     *
     * @param \GaletteObjectsLend\Filters\ObjectsList $filters Filters instance
     */
    protected function testDefaults(\GaletteObjectsLend\Filters\ObjectsList $filters): void
    {
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::ORDERBY_NAME, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());
        $this->assertNull($filters->filter_str);
        $this->assertNull($filters->category_filter);
        $this->assertNull($filters->active_filter);
        $this->assertNull($filters->field_filter);
        $this->assertSame([], $filters->selected);
    }

    /**
     * Test creation
     */
    public function testCreate(): void
    {
        $filters = new \GaletteObjectsLend\Filters\ObjectsList();

        $this->testDefaults($filters);

        //change order field
        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS;
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //same order field again: direction inverted
        $filters->orderby = \GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS;
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //not existing order, same kept
        $filters->setDirection('abcde');
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());
        $this->expectLogEntry(
            \Analog::WARNING,
            '[GaletteObjectsLend\Filters\ObjectsList|Pagination] "abcde" is not a valid backing value for enum Galette\Enums\SQLOrder'
        );

        //change direction only
        $filters->setDirection(\Galette\Enums\SQLOrder::ASC);
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::ORDERBY_STATUS, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //set string filter
        $filters->filter_str = 'a string';
        $this->assertSame('a string', $filters->filter_str);

        //Set activity filter
        $filters->active_filter = \GaletteObjectsLend\Repository\Objects::INACTIVE_OBJECTS;
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::INACTIVE_OBJECTS, $filters->active_filter);

        //out of known values, no change
        $filters->active_filter = 42;
        $this->expectLogEntry(
            \Analog::WARNING,
            '[ObjectsList] Value for active filter should be either 1, 1 or 2 (42 given)'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::INACTIVE_OBJECTS, $filters->active_filter);

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_SERIAL;
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::FILTER_SERIAL, $filters->field_filter);

        //reinit and test defaults are back
        $filters->reinit();
        $this->testDefaults($filters);
    }

    /**
     * Test setting non existing filter
     */
    public function testSetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $filters->non_existing = 42;
    }

    /**
     * Test getting non existing filter
     */
    public function testGetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $this->assertNull($filters->non_existing);
    }
}
