<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\tests\units;

use Analog\Analog;
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

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where->like('info_cotis', 'Rent of %');
        $this->zdb->execute($delete);
        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Set plugin preferences
     *
     * @param bool $member_rent  Allow members to borrow objects
     * @param bool $auto_contrib Generate a contribution on borrow
     */
    private function setPrefs(bool $member_rent, bool $auto_contrib = false): void
    {
        $prefs = new Preferences($this->zdb);
        $values = $this->orig_prefs;
        $values[Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT] = (int)$member_rent;
        $values[Preferences::PARAM_AUTO_GENERATE_CONTRIBUTION] = (int)$auto_contrib;
        $values[Preferences::PARAM_GENERATED_CONTRIBUTION_TYPE_ID] = 5;
        $values[Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT] = 'Rent of {NAME} ({SERIAL_NUMBER})';
        $this->assertTrue($prefs->store($values));
    }

    /**
     * Log in given member
     *
     * @param array<string,mixed> $mdata Member data
     */
    private function logMember(array $mdata): void
    {
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Get the rent the test object points to
     */
    private function getObjectRentId(): ?int
    {
        $select = $this->zdb->select(LEND_PREFIX . LendObject::TABLE);
        $select->where([LendObject::PK => $this->object_id]);
        $rent_id = $this->zdb->execute($select)->current()->{LendRent::PK};
        return $rent_id === null ? null : (int)$rent_id;
    }

    /**
     * Get stored contributions
     *
     * @return array<int, \ArrayObject<string, mixed>>
     */
    private function getContributions(): array
    {
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE);
        $contribs = [];
        foreach ($this->zdb->execute($select) as $row) {
            $contribs[] = $row;
        }
        return $contribs;
    }

    /**
     * Lend test object to a member, bypassing controller
     *
     * @param int $member_id Member ID
     */
    private function lendObject(int $member_id): void
    {
        $rent = new LendRent();
        $rent->object_id = $this->object_id;
        $rent->status_id = $this->lent_status;
        $rent->adherent_id = $member_id;
        //make sure the rent is older than the ones created from controller
        $rent->date_begin = (new \DateTime('-1 day'))->format('Y-m-d H:i:s');
        $this->assertTrue($rent->store());
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
     * Build a return request
     *
     * @param array<string,mixed> $data Posted data
     */
    private function returnRequest(array $data = []): \Slim\Psr7\Request
    {
        $request = $this->createRequest(
            route_name: 'objectslend_object_doreturn',
            route_args: ['id' => (string)$this->object_id],
            method: 'POST'
        );
        return $request->withParsedBody(
            $data + [
                'status' => (string)$this->instock_status,
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

    /**
     * An object already lent cannot be borrowed again
     */
    public function testTakeLentObject(): void
    {
        $this->setPrefs(true);
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $mdata = $this->dataAdherentTwo();
        $this->getMemberTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $test_response = $this->app->handle($this->takeRequest([]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('objectslend_objects')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['This object cannot be borrowed.']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to borrow an unavailable object');

        //current lend has not been closed
        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
        $this->assertSame('', $rents[0]->date_end ?? '');
    }

    /**
     * Borrowing requires a "not in stock" status
     */
    public function testTakeWithInStockStatus(): void
    {
        $this->setPrefs(false);

        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->takeRequest(['status' => (string)$this->instock_status])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['This object cannot be borrowed.']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to borrow an unavailable object');
        $this->assertCount(0, $this->getRents());
    }

    /**
     * Staff can always give an object back
     */
    public function testAdminReturnWhenMemberRentDisabled(): void
    {
        $this->setPrefs(false);
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Test object has been returned :)']]);

        $rents = $this->getRents();
        $this->assertCount(2, $rents);
        $this->assertTrue($rents[0]->in_stock);
    }

    /**
     * A member can only give back objects they hold
     */
    public function testMemberReturnObjectHeldByAnotherMember(): void
    {
        $this->setPrefs(true);
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $mdata = $this->dataAdherentTwo();
        $this->getMemberTwo();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have rights to return objects!']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to return an object without appropriate rights!');
        $this->assertCount(1, $this->getRents());
    }

    /**
     * A member gives back an object they hold
     */
    public function testMemberReturnOwnObject(): void
    {
        $this->setPrefs(true);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Test object has been returned :)']]);
        $this->assertCount(2, $this->getRents());
    }

    /**
     * Giving back requires an "in stock" status, and a lent object
     */
    public function testReturnInvalid(): void
    {
        $this->setPrefs(false);
        $this->logSuperAdmin();

        //object is not lent
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['This object cannot be returned.']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to return an object that is not lent');
        $this->assertCount(0, $this->getRents());

        //wrong status
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);
        $test_response = $this->app->handle(
            $this->returnRequest(['status' => (string)$this->lent_status])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['This object cannot be returned.']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to return an object that is not lent');
        $this->assertCount(1, $this->getRents());
    }

    /**
     * Count stored objects
     */
    private function countObjects(): int
    {
        $select = $this->zdb->select(LEND_PREFIX . LendObject::TABLE);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Opening the clone link only asks for a confirmation
     */
    public function testCloneAsksConfirmation(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(
            route_name: 'objectslend_object_clone',
            route_args: ['id' => (string)$this->object_id]
        );
        $test_response = $this->app->handle($request);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString(
            'action="' . $this->routeparser->urlFor('objectslend_object_doclone', ['id' => (string)$this->object_id])
                . '" method="post"',
            (string)$test_response->getBody()
        );
        $this->assertSame(1, $this->countObjects());
    }

    /**
     * Confirming the clone stores a copy
     */
    public function testDoClone(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(
            route_name: 'objectslend_object_doclone',
            route_args: ['id' => (string)$this->object_id],
            method: 'POST'
        );
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(2, $this->countObjects());
    }

    /**
     * Objects can be searched on another field than their name
     */
    public function testFilterOnField(): void
    {
        $object = new LendObject($this->zdb, $this->object_id);
        $object->serial_number = 'SN-4242';
        $this->assertTrue($object->store());

        $this->logSuperAdmin();
        $request = $this->createRequest(
            route_name: 'objectslend_filter_objects',
            method: 'POST'
        );
        $request = $request->withParsedBody([
            'filter_str' => '4242',
            'field_filter' => (string)\GaletteObjectsLend\Repository\Objects::FILTER_SERIAL
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());

        $filters = $this->session->objectslend_filter_objects;
        $this->assertSame(\GaletteObjectsLend\Repository\Objects::FILTER_SERIAL, $filters->field_filter);

        $objects = new \GaletteObjectsLend\Repository\Objects($this->zdb, new Preferences($this->zdb), $filters);
        $list = $objects->getObjectsList(true);
        $this->assertCount(1, $list);
    }

    /**
     * Add an object from the form
     *
     * @param string $first_status First status posted
     */
    private function addObject(string $first_status): LendObject
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(
            route_name: 'objectslend_object_action_add',
            method: 'POST'
        );
        $request = $request->withParsedBody([
            'name' => 'New object',
            'description' => '',
            'serial' => '',
            'price' => '',
            'rent_price' => '',
            'dimension' => '',
            'weight' => '',
            '1st_status' => $first_status
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());

        $select = $this->zdb->select(LEND_PREFIX . LendObject::TABLE);
        $select->where(['name' => 'New object']);
        $result = $this->zdb->execute($select);
        $this->assertSame(1, $result->count());
        return new LendObject($this->zdb, (int)$result->current()->{LendObject::PK});
    }

    /**
     * No rent is stored when no first status has been chosen
     */
    public function testAddWithoutFirstStatus(): void
    {
        $object = $this->addObject('0');
        $this->assertCount(0, LendRent::getRentsForObjectId($object->getId()));
    }

    /**
     * A rent is stored with the chosen first status
     */
    public function testAddWithFirstStatus(): void
    {
        $object = $this->addObject((string)$this->instock_status);
        $rents = LendRent::getRentsForObjectId($object->getId());
        $this->assertCount(1, $rents);
        $this->assertSame($this->instock_status, $rents[0]->status_id);
    }

    /**
     * A simple member cannot borrow when members are not allowed to
     */
    public function testMemberCannotTakeWhenMemberRentDisabled(): void
    {
        $this->setPrefs(false);
        $mdata = $this->dataAdherentOne();
        $this->getMemberOne();

        $this->logMember($mdata);
        $test_response = $this->app->handle($this->takeRequest([]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('objectslend_objects')]],
            $test_response->getHeaders()
        );
        $this->expectFlashData(['error_detected' => ['You do not have rights to borrow objects!']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to borrow an object without appropriate rights!');
        $this->assertCount(0, $this->getRents());
    }

    /**
     * Staff can borrow in the name of a member, even when members are not allowed to
     */
    public function testStaffTakeForAMember(): void
    {
        $this->setPrefs(false);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->getStaffMember($member_one);

        $this->logMember($mdata);
        $this->assertTrue($this->login->isStaff());
        $test_response = $this->app->handle(
            $this->takeRequest([\Galette\Entity\Adherent::PK => (string)$member_two->id])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);

        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_two->id, $rents[0]->adherent_id);
    }

    /**
     * Staff borrows for themselves when no member is chosen
     */
    public function testStaffTakeForThemselves(): void
    {
        $this->setPrefs(false);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $this->getStaffMember($member_one);

        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->takeRequest([\Galette\Entity\Adherent::PK => ''])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);

        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
    }

    /**
     * An inactive object cannot be borrowed
     */
    public function testTakeInactiveObject(): void
    {
        $object = new LendObject($this->zdb, $this->object_id);
        $object->is_active = false;
        $this->assertTrue($object->store());
        $this->setPrefs(false);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->takeRequest([]));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['This object cannot be borrowed.']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to borrow an unavailable object');
        $this->assertCount(0, $this->getRents());
    }

    /**
     * Borrowing closes the current rent, and the object points to the new one
     */
    public function testTakeClosesCurrentRent(): void
    {
        $this->setPrefs(true);
        $rent = new LendRent();
        $rent->object_id = $this->object_id;
        $rent->status_id = $this->instock_status;
        $rent->date_begin = (new \DateTime('-1 day'))->format('Y-m-d H:i:s');
        $this->assertTrue($rent->store());
        $this->assertSame($rent->rent_id, $this->getObjectRentId());

        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->takeRequest(['expected_return' => '2030-01-15'])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);

        $rents = $this->getRents();
        $this->assertCount(2, $rents);
        //new rent
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
        $this->assertSame($this->lent_status, $rents[0]->status_id);
        $this->assertFalse($rents[0]->in_stock);
        $this->assertSame('', $rents[0]->date_end ?? '');
        $this->assertStringStartsWith('2030-01-15', $rents[0]->date_forecast);
        $this->assertSame($rents[0]->rent_id, $this->getObjectRentId());
        //previous one is closed
        $this->assertSame($rent->rent_id, $rents[1]->rent_id);
        $this->assertNotNull($rents[1]->date_end);
    }

    /**
     * An ajax borrow answers with JSON
     */
    public function testTakeAjax(): void
    {
        $this->setPrefs(false);
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->takeRequest(['mode' => 'ajax']));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['success' => 'true'], json_decode((string)$test_response->getBody(), true));
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);
        $this->assertCount(1, $this->getRents());
    }

    /**
     * Set test object prices
     *
     * @param float $rent_price Rent price
     */
    private function setRentPrice(float $rent_price): void
    {
        $object = new LendObject($this->zdb, $this->object_id);
        $object->rent_price = $rent_price;
        $object->serial_number = 'SN-42';
        $this->assertTrue($object->store());
    }

    /**
     * Staff can set the price of the generated contribution
     */
    public function testTakeGeneratesContributionWithPostedPrice(): void
    {
        $this->setPrefs(false, true);
        $this->setRentPrice(3.5);
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->takeRequest([
                \Galette\Entity\Adherent::PK => (string)$member_one->id,
                'rent_price' => '12,50',
                'payment_type' => (string)\Galette\Entity\PaymentType::CASH
            ])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => [
            'Contribution has been successfully stored',
            'You have just borrowed Test object :)'
        ]]);

        $contribs = $this->getContributions();
        $this->assertCount(1, $contribs);
        $this->assertEquals(12.5, $contribs[0]->montant_cotis);
        $this->assertSame($member_one->id, (int)$contribs[0]->{\Galette\Entity\Adherent::PK});
        $this->assertSame(5, (int)$contribs[0]->{\Galette\Entity\ContributionsTypes::PK});
        $this->assertSame(\Galette\Entity\PaymentType::CASH, (int)$contribs[0]->type_paiement_cotis);
        $this->assertSame('Rent of Test object (SN-42)', $contribs[0]->info_cotis);
    }

    /**
     * A member borrowing a paid object gets no contribution, and an error page
     *
     * Known bug: the contribution is checked with the rights of the member, who
     * cannot create one; the result of check() is ignored and store() throws,
     * after the rent has been stored.
     */
    public function testMemberTakeWithContributionFails(): void
    {
        $this->setPrefs(true, true);
        $this->setRentPrice(3.5);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();

        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->takeRequest([
                'rent_price' => '0,01',
                'payment_type' => (string)\Galette\Entity\PaymentType::CASH
            ])
        );
        $this->assertSame(500, $test_response->getStatusCode());
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');
        $this->expectFlashData([]);

        $this->assertCount(0, $this->getContributions());
        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->adherent_id);
    }

    /**
     * No contribution is generated for a free object
     */
    public function testTakeFreeObjectGeneratesNoContribution(): void
    {
        $this->setPrefs(false, true);
        $this->setRentPrice(0);
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->takeRequest([
                \Galette\Entity\Adherent::PK => (string)$member_one->id,
                'rent_price' => '',
                'payment_type' => (string)\Galette\Entity\PaymentType::CASH
            ])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['You have just borrowed Test object :)']]);
        $this->assertCount(0, $this->getContributions());
        $this->assertCount(1, $this->getRents());
    }

    /**
     * Staff can give an object back when members are not allowed to borrow
     */
    public function testStaffReturnWhenMemberRentDisabled(): void
    {
        $this->setPrefs(false);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->getStaffMember($member_one);
        $this->lendObject($member_two->id);

        $this->logMember($mdata);
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Test object has been returned :)']]);

        $rents = $this->getRents();
        $this->assertCount(2, $rents);
        $this->assertTrue($rents[0]->in_stock);
        //returned object is held by no one
        $this->assertNull($rents[0]->adherent_id);
        $this->assertSame('', $rents[0]->date_end ?? '');
        $this->assertSame($rents[0]->rent_id, $this->getObjectRentId());
        $this->assertNotNull($rents[1]->date_end);
    }

    /**
     * A member cannot give back an object they hold when members are not allowed to borrow
     */
    public function testMemberReturnOwnObjectWhenMemberRentDisabled(): void
    {
        $this->setPrefs(false);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->logMember($mdata);
        $test_response = $this->app->handle($this->returnRequest());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have rights to return objects!']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to return an object without appropriate rights!');
        $this->assertCount(1, $this->getRents());
    }

    /**
     * An ajax return answers with JSON
     */
    public function testReturnAjax(): void
    {
        $this->setPrefs(false);
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->returnRequest(['mode' => 'ajax']));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame(['success' => 'true'], json_decode((string)$test_response->getBody(), true));
        $this->expectFlashData(['success_detected' => ['Test object has been returned :)']]);
        $this->assertCount(2, $this->getRents());
    }

    /**
     * Build an update status request
     *
     * @param array<string,mixed> $data Posted data
     */
    private function updateStatusRequest(array $data): \Slim\Psr7\Request
    {
        $request = $this->createRequest(
            route_name: 'objectslend_object_updatestatus',
            route_args: ['id' => (string)$this->object_id],
            method: 'POST'
        );
        return $request->withParsedBody($data);
    }

    /**
     * Staff can change the status of an object, and who holds it
     */
    public function testStaffUpdateStatus(): void
    {
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $this->getStaffMember($member_one);
        $this->lendObject($member_one->id);

        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->updateStatusRequest([
                'new_status' => (string)$this->lent_status,
                'new_adh' => (string)$member_two->id,
                'new_comment' => 'Handed over'
            ])
        );
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('objectslend_object_edit', ['id' => (string)$this->object_id])]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Status has been updated']]);

        $rents = $this->getRents();
        $this->assertCount(2, $rents);
        $this->assertSame($member_two->id, $rents[0]->adherent_id);
        $this->assertSame($this->lent_status, $rents[0]->status_id);
        $this->assertSame('', $rents[0]->date_end ?? '');
        $this->assertSame($rents[0]->rent_id, $this->getObjectRentId());
        //comment goes to the closed rent
        $this->assertNotNull($rents[1]->date_end);
        $this->assertSame('Handed over', $rents[1]->comments);

        //no member: object is held by no one
        $test_response = $this->app->handle(
            $this->updateStatusRequest([
                'new_status' => (string)$this->instock_status,
                'new_adh' => '',
                'new_comment' => ''
            ])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['success_detected' => ['Status has been updated']]);
        $rents = $this->getRents();
        $this->assertCount(3, $rents);
        //rents have been created in the same second, their order is not reliable
        $current = array_values(array_filter(
            $rents,
            fn(LendRent $rent) => $rent->rent_id === $this->getObjectRentId()
        ));
        $this->assertCount(1, $current);
        $this->assertNull($current[0]->adherent_id);
        $this->assertTrue($current[0]->in_stock);
    }

    /**
     * Only staff can change the status of an object
     */
    public function testMemberCannotUpdateStatus(): void
    {
        $this->setPrefs(true);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->updateStatusRequest([
                'new_status' => (string)$this->instock_status,
                'new_adh' => '',
                'new_comment' => ''
            ])
        );
        $this->expectAuthMiddlewareRefused($test_response);
        $this->assertCount(1, $this->getRents());
    }
}
