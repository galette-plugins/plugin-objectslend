<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\tests\units;

use Galette\Core\Plugins\FixturesContext;
use Galette\Tests\GaletteTestCase;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;

/**
 * Fixtures tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Fixtures extends GaletteTestCase
{
    protected int $seed = 20261004130000;
    protected bool $load_plugins = true;

    /**
     * Set up tests: sample data need an empty catalog
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->emptyCatalog();
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->emptyCatalog();
        parent::tearDown();
    }

    /**
     * Remove objects, rents, categories and statuses
     */
    private function emptyCatalog(): void
    {
        $this->zdb->execute($this->zdb->update(LEND_PREFIX . LendObject::TABLE)->set([LendRent::PK => null]));
        foreach ([LendRent::TABLE, LendObject::TABLE, LendCategory::TABLE, LendStatus::TABLE] as $table) {
            $this->zdb->execute($this->zdb->delete(LEND_PREFIX . $table));
        }
    }

    /**
     * Test seeding and cleaning fixtures
     */
    public function testFixtures(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->logSuperAdmin();

        $context = new FixturesContext(
            zdb: $this->zdb,
            login: $this->login,
            preferences: $this->preferences,
            history: $this->history,
            plugins: $this->plugins,
            members: ['one' => $member_one->id, 'two' => $member_two->id]
        );

        $fixtures = new \GaletteObjectsLend\Fixtures();
        $this->assertMatchesRegularExpression(
            '/^Created 5 categories, 6 statuses, \d+ objects and \d+ rents$/',
            $fixtures->seedFixtures($context)
        );

        //rents are given to context members only
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE)->columns(['adherent_id'])->quantifier('DISTINCT');
        $select->where->isNotNull('adherent_id');
        $members = [];
        foreach ($this->zdb->execute($select) as $row) {
            $members[] = (int)$row->adherent_id;
        }
        sort($members);
        $this->assertSame([$member_one->id, $member_two->id], $members);

        $fixtures->cleanFixtures($context);
        foreach ([LendRent::TABLE, LendObject::TABLE, LendCategory::TABLE, LendStatus::TABLE] as $table) {
            $this->assertSame(0, $this->zdb->execute($this->zdb->select(LEND_PREFIX . $table))->count(), $table);
        }
    }
}
