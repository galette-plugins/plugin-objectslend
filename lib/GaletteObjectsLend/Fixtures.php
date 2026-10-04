<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend;

use Galette\Core\Plugins\FixturesContext;
use Galette\Core\Plugins\FixturesProviderInterface;

/**
 * Sample data for fixture members, run by galette:seed-fixtures
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Fixtures implements FixturesProviderInterface
{
    /**
     * Load sample data, rents given to fixture members
     *
     * @param FixturesContext $context Fixtures context
     */
    public function seedFixtures(FixturesContext $context): string
    {
        $created = (new SampleData($context->zdb))->load(array_values($context->members));
        return sprintf(
            'Created %d categories, %d statuses, %d objects and %d rents',
            $created['categories'],
            $created['statuses'],
            $created['objects'],
            $created['rents']
        );
    }

    /**
     * Remove sample data
     *
     * @param FixturesContext $context Fixtures context
     */
    public function cleanFixtures(FixturesContext $context): void
    {
        (new SampleData($context->zdb))->remove();
    }
}
