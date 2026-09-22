<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Entity\Preferences;

/**
 * Objects controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ObjectsController extends GaletteRoutingTestCase
{
    protected int $seed = 20260922190512;
    protected bool $load_plugins = true;

    private int $instock_status;
    private int $lent_status;
    private int $object_id;
    /** @var array<string,mixed> */
    private array $orig_prefs;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();

        $prefs = new Preferences($this->zdb);
        $this->orig_prefs = $prefs->getPreferences();

        $status = new LendStatus($this->zdb);
        $status->status_text = 'In stock';
        $status->in_stock = true;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $this->instock_status = $status->status_id;

        $status = new LendStatus($this->zdb);
        $status->status_text = 'Lent';
        $status->in_stock = false;
        $status->is_active = true;
        $this->assertTrue($status->store());
        $this->lent_status = $status->status_id;

        $object = new LendObject($this->zdb);
        $object->name = 'Test object';
        $this->assertTrue($object->store());
        $this->object_id = $object->object_id;
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();

        $prefs = new Preferences($this->zdb);
        $prefs->store($this->orig_prefs);

        $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE);
        $update->set([LendRent::PK => null]);
        $this->zdb->execute($update);

        $delete = $this->zdb->delete(LEND_PREFIX . LendRent::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . LendObject::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . LendStatus::TABLE);
        $this->zdb->execute($delete);

        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Set plugin preferences
     *
     * @param bool $member_rent Allow members to borrow objects
     */
    private function setPrefs(bool $member_rent): void
    {
        $prefs = new Preferences($this->zdb);
        $values = $this->orig_prefs;
        $values[Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT] = (int)$member_rent;
        $values[Preferences::PARAM_AUTO_GENERATE_CONTRIBUTION] = 0;
        $this->assertTrue($prefs->store($values));
    }


    /**
     * Get rents of test object, most recent first
     *
     * @return LendRent[]
     */
    private function getRents(): array
    {
        return LendRent::getRentsForObjectId($this->object_id);
    }

    /**
     * Build a take request
     *
     * @param array<string,mixed> $data Posted data
     */
    private function takeRequest(array $data): \Slim\Psr7\Request
    {
        $request = $this->createRequest(
            route_name: 'objectslend_object_dotake',
            route_args: ['id' => (string)$this->object_id],
            method: 'POST'
        );
        return $request->withParsedBody(
            $data + [
                'status' => (string)$this->lent_status,
                'expected_return' => date('Y-m-d'),
                'mode' => ''
            ]
        );
    }


    /**
     * A simple member cannot borrow in the name of someone else
     */
    public function testMemberCannotTakeForAnotherMember(): void
    {
        $this->setPrefs(true);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $test_response = $this->app->handle(
            $this->takeRequest([\Galette\Entity\Adherent::PK => (string)$member_two->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);

        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
    }

    /**
     * Staff can borrow in the name of a member
     */
    public function testAdminTakeForAMember(): void
    {
        $this->setPrefs(false);
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->takeRequest([\Galette\Entity\Adherent::PK => (string)$member_one->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);

        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
    }






}
