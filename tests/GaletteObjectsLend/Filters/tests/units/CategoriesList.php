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
 * Categories filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CategoriesList extends GaletteTestCase
{
    /**
     * Test filter defaults values
     *
     * @param \GaletteObjectsLend\Filters\CategoriesList $filters Filters instance
     */
    protected function testDefaults(\GaletteObjectsLend\Filters\CategoriesList $filters): void
    {
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::ORDERBY_NAME, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());
        $this->assertNull($filters->filter_str);
        $this->assertNull($filters->active_filter);
        $this->assertNull($filters->not_empty);
        $this->assertNull($filters->objects_filters);
    }

    /**
     * Test creation
     */
    public function testCreate(): void
    {
        $filters = new \GaletteObjectsLend\Filters\CategoriesList();

        $this->testDefaults($filters);

        //change order field
        $filters->orderby = \GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY;
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //same order field again: direction inverted
        $filters->orderby = \GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY;
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //not existing order, same kept
        $filters->setDirection('abcd');
        $this->expectLogEntry(
            \Analog::WARNING,
            '[GaletteObjectsLend\Filters\CategoriesList|Pagination] "abcd" is not a valid backing value for enum Galette\Enums\SQLOrder'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::DESC->value, $filters->getDirection());

        //change direction only
        $filters->setDirection(\Galette\Enums\SQLOrder::ASC);
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::ORDERBY_ACTIVITY, $filters->orderby);
        $this->assertSame(\Galette\Enums\SQLOrder::ASC->value, $filters->getDirection());

        //set string filter
        $filters->filter_str = 'a string';
        $this->assertSame('a string', $filters->filter_str);

        //Set activity filter
        $filters->active_filter = \GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES;
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES, $filters->active_filter);

        //cast is forced
        $filters->active_filter = \GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES;
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES, $filters->active_filter);

        //out of known values, no change
        $filters->active_filter = 42;
        $this->expectLogEntry(
            \Analog::WARNING,
            '[CategoriesList] Value for active filter should be either 0, 1 or 2 (42 given)
'
        );
        $this->assertSame(\GaletteObjectsLend\Repository\Categories::INACTIVE_CATEGORIES, $filters->active_filter);

        $ofilters = new \GaletteObjectsLend\Filters\ObjectsList();
        $this->assertInstanceOf($filters::class, $filters->setObjectsFilter($ofilters));
        $this->assertSame($ofilters, $filters->objects_filters);

        //reinit and test defaults are back
        $filters->reinit();
        $this->testDefaults($filters);
    }

    /**
     * Test setting non existing filter
     */
    public function testSetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\CategoriesList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $filters->non_existing = 42;
    }

    /**
     * Test getting non existing filter
     */
    public function testGetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\CategoriesList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $this->assertNull($filters->non_existing);
    }
}
