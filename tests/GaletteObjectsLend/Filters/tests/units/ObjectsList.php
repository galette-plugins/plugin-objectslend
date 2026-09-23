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
            Analog::WARNING,
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
            Analog::WARNING,
            '[GaletteObjectsLend\Filters\ObjectsList] Value for active_filter should be one of 0, 1, 2 (42 given)'
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
        $filters->non_existing = 42; // @phpstan-ignore property.notFound
    }

    /**
     * Test getting non existing filter
     */
    public function testGetNotExisting(): void
    {
        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $this->testDefaults($filters);

        $this->expectException(\RuntimeException::class);
        $this->assertNull($filters->non_existing); // @phpstan-ignore property.notFound
    }

    /**
     * Test search highlighting
     */
    public function testHighlight(): void
    {
        $filters = new \GaletteObjectsLend\Filters\ObjectsList();
        $name = htmlspecialchars('<script>alert("name")</script> (test)', ENT_QUOTES);
        $this->assertSame('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; (test)', $name);

        //no search
        $this->assertSame($name, $filters->highlight($name, 'name'));

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_NAME;
        $filters->filter_str = 'object';
        $this->assertSame('An <span class="search">object</span> (edited)', $filters->highlight('An object (edited)', 'name'));
        $this->assertSame('An <span class="search">object</span> description', $filters->highlight('An object description', 'description'));
        //search does not concern other fields
        $this->assertSame('object', $filters->highlight('object', 'serial_number'));

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_SERIAL;
        $filters->filter_str = 'abc';
        $this->assertSame('SE-<span class="search">aBc</span>-RI@L', $filters->highlight('SE-aBc-RI@L', 'serial_number'));
        $this->assertSame('abc', $filters->highlight('abc', 'name'));

        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_DIM;
        $filters->filter_str = '50';
        $this->assertSame('10x<span class="search">50</span>', $filters->highlight('10x50', 'dimension'));

        //regexp special chars are not interpreted
        $filters->field_filter = \GaletteObjectsLend\Repository\Objects::FILTER_NAME;
        $filters->filter_str = '(test';
        $this->assertSame(
            '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; <span class="search">(test</span>)',
            $filters->highlight($name, 'name')
        );
        $filters->filter_str = '(test)';
        $this->assertSame(
            '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt; <span class="search">(test)</span>',
            $filters->highlight($name, 'name')
        );
        //search matches escaped content, and highlighting does not break it
        $filters->filter_str = 'script>';
        $this->assertSame(
            '&lt;<span class="search">script&gt;</span>alert(&quot;name&quot;)&lt;/<span class="search">script&gt;</span> (test)',
            $filters->highlight($name, 'name')
        );
        $filters->filter_str = 'a & b';
        $this->assertSame('<span class="search">A &amp; B</span>', $filters->highlight('A &amp; B', 'description'));

        //never inside HTML tags
        $description = '<p>Nice <strong>object</strong></p>';
        $filters->filter_str = 'strong';
        $this->assertSame($description, $filters->highlight($description, 'description'));
        $filters->filter_str = 'object';
        $this->assertSame(
            '<p>Nice <strong><span class="search">object</span></strong></p>',
            $filters->highlight($description, 'description')
        );
    }
}
