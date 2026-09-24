<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Repository\Rents;

/**
 * Sample data tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SampleData extends GaletteTestCase
{
    protected int $seed = 20260924143015;
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
     * Count rows of a plugin table
     *
     * @param string $table Table name, without prefixes
     */
    private function countRows(string $table): int
    {
        return $this->zdb->execute($this->zdb->select(LEND_PREFIX . $table))->count();
    }

    /**
     * Load sample data
     */
    public function testLoad(): void
    {
        $data = json_decode(
            (string)file_get_contents(\GaletteObjectsLend\SampleData::FILE),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertIsArray($data);

        //an existing category is reused
        $category = new LendCategory($this->zdb);
        $category->setName($data['categories'][0]['name'])->setActive(true);
        $category->store();

        $member = $this->getMemberOne();
        $sample = new \GaletteObjectsLend\SampleData($this->zdb);
        $this->assertFalse($sample->hasObjects());

        $created = $sample->load([(int)$member->id]);
        $this->assertTrue($sample->hasObjects());

        $nb_rents = array_sum(array_map(fn($o) => count($o['rents']), $data['objects']));
        $this->assertSame(
            [
                'categories' => count($data['categories']) - 1,
                'statuses' => count($data['statuses']),
                'objects' => count($data['objects']),
                'rents' => $nb_rents
            ],
            $created
        );
        $this->assertSame(count($data['categories']), $this->countRows(LendCategory::TABLE));
        $this->assertSame(count($data['objects']), $this->countRows(LendObject::TABLE));
        $this->assertSame($nb_rents, $this->countRows(LendRent::TABLE));

        //every object has a current rent, the last one, still open
        $rents_repo = new Rents($this->zdb);
        foreach ($this->zdb->execute($this->zdb->select(LEND_PREFIX . LendObject::TABLE)) as $row) {
            $this->assertNotNull($row->rent_id, $row->name);
            $rents = $rents_repo->getForObject((int)$row->object_id);
            $open = array_filter($rents, fn(LendRent $rent) => $rent->getDateEnd() === '');
            $this->assertCount(1, $open, $row->name);
            $this->assertSame((int)$row->rent_id, array_values($open)[0]->getId());
        }

        //rents with a member are given to the only one available
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE);
        $select->where->isNotNull('adherent_id');
        foreach ($this->zdb->execute($select) as $row) {
            $this->assertSame((int)$member->id, (int)$row->adherent_id);
        }
    }

    /**
     * Rents are anonymous without members
     */
    public function testLoadWithoutMembers(): void
    {
        (new \GaletteObjectsLend\SampleData($this->zdb))->load([]);
        $select = $this->zdb->select(LEND_PREFIX . LendRent::TABLE);
        $select->where->isNotNull('adherent_id');
        $this->assertSame(0, $this->zdb->execute($select)->count());
        $this->assertGreaterThan(0, $this->countRows(LendRent::TABLE));
    }

    /**
     * Nothing is kept when loading fails
     */
    public function testInvalidFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lend');
        file_put_contents($file, '{"categories": []}');
        try {
            $this->expectException(\RuntimeException::class);
            (new \GaletteObjectsLend\SampleData($this->zdb, $file))->load([]);
        } finally {
            unlink($file);
        }
    }
}
