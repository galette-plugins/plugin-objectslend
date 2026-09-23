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
        $status->setText('In stock');
        $status->setInStock(true);
        $status->setActive(true);
        $this->assertTrue($status->store());
        $this->instock_status = $status->getId();

        $status = new LendStatus($this->zdb);
        $status->setText('Lent');
        $status->setInStock(false);
        $status->setActive(true);
        $this->assertTrue($status->store());
        $this->lent_status = $status->getId();

        $object = new LendObject($this->zdb);
        $object->setName('Test object');
        $this->assertTrue($object->store());
        $this->object_id = $object->getId();
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
     * @return array<int, array<string, mixed>>
     */
    private function getContributions(): array
    {
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE);
        $contribs = [];
        foreach ($this->zdb->execute($select) as $row) {
            $contribs[] = $row->getArrayCopy();
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
        $rent = new LendRent($this->zdb);
        $rent->setObjectId($this->object_id);
        $rent->setStatusId($this->lent_status);
        $rent->setAdherentId($member_id);
        //make sure the rent is older than the ones created from controller
        $rent->setDateBegin((new \DateTime('-1 day'))->format('Y-m-d H:i:s'));
        $this->storeCurrentRent($rent);
    }

    /**
     * Store a rent as the test object current one, bypassing controller
     *
     * @param LendRent $rent Rent
     */
    private function storeCurrentRent(LendRent $rent): void
    {
        $this->assertTrue($rent->store());
        $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE)
            ->set([LendRent::PK => $rent->getId()])
            ->where([LendObject::PK => $this->object_id]);
        $this->zdb->execute($update);
    }

    /**
     * Get rents of test object, most recent first
     *
     * @return LendRent[]
     */
    private function getRents(): array
    {
        return (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($this->object_id);
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
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
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
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
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
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
        $this->assertSame('', $rents[0]->getDateEnd());
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
        $this->assertTrue($rents[0]->isInStock());
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
        $object->setSerialNumber('SN-4242');
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
        $this->assertCount(0, (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($object->getId()));
    }

    /**
     * A rent is stored with the chosen first status
     */
    public function testAddWithFirstStatus(): void
    {
        $object = $this->addObject((string)$this->instock_status);
        $rents = (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($object->getId());
        $this->assertCount(1, $rents);
        $this->assertSame($this->instock_status, $rents[0]->getStatusId());
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
        $this->assertSame($member_two->id, $rents[0]->getAdherentId());
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
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
    }

    /**
     * An inactive object cannot be borrowed
     */
    public function testTakeInactiveObject(): void
    {
        $object = new LendObject($this->zdb, $this->object_id);
        $object->setActive(false);
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
        $rent = new LendRent($this->zdb);
        $rent->setObjectId($this->object_id);
        $rent->setStatusId($this->instock_status);
        $rent->setDateBegin((new \DateTime('-1 day'))->format('Y-m-d H:i:s'));
        $this->storeCurrentRent($rent);

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
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
        $this->assertSame($this->lent_status, $rents[0]->getStatusId());
        $this->assertFalse($rents[0]->isInStock());
        $this->assertSame('', $rents[0]->getDateEnd());
        $this->assertStringStartsWith('2030-01-15', $rents[0]->getDateForecast());
        $this->assertSame($rents[0]->getId(), $this->getObjectRentId());
        //previous one is closed
        $this->assertSame($rent->getId(), $rents[1]->getId());
        $this->assertNotSame('', $rents[1]->getDateEnd());
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
        $object->setRentPrice($rent_price);
        $object->setSerialNumber('SN-42');
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
        $this->assertEquals(12.5, $contribs[0]['montant_cotis']);
        $this->assertSame($member_one->id, (int)$contribs[0][\Galette\Entity\Adherent::PK]);
        $this->assertSame(5, (int)$contribs[0][\Galette\Entity\ContributionsTypes::PK]);
        $this->assertSame(\Galette\Entity\PaymentType::CASH, (int)$contribs[0]['type_paiement_cotis']);
        $this->assertSame('Rent of Test object (SN-42)', $contribs[0]['info_cotis']);
    }

    /**
     * Test a member borrowing a paid object gets a contribution at the object price
     */
    public function testMemberTakeGeneratesContribution(): void
    {
        $this->setPrefs(true, true);
        $this->setRentPrice(3.5);
        $mdata = $this->dataAdherentOne();
        $member_one = $this->getMemberOne();

        $this->logMember($mdata);
        $test_response = $this->app->handle(
            $this->takeRequest([
                //members cannot change price
                'rent_price' => '0,01',
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
        $this->assertEquals(3.5, $contribs[0]['montant_cotis']);
        $this->assertSame($member_one->id, (int)$contribs[0][\Galette\Entity\Adherent::PK]);
        $rents = $this->getRents();
        $this->assertCount(1, $rents);
        $this->assertSame($member_one->id, $rents[0]->getAdherentId());
    }

    /**
     * Test lend is refused when contribution cannot be stored
     *
     * Rollback itself is checked in LendService tests, this one runs in a transaction.
     */
    public function testTakeWithInvalidContribution(): void
    {
        $this->setPrefs(false, true);
        $this->setRentPrice(3.5);
        $member_one = $this->getMemberOne();

        $this->logSuperAdmin();
        $test_response = $this->app->handle(
            $this->takeRequest([
                \Galette\Entity\Adherent::PK => (string)$member_one->id,
                'payment_type' => '999999'
            ])
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(Analog::WARNING, 'Unknown payment type 999999');
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');
        $this->expectLogEntry(Analog::ERROR, 'Unable to generate contribution for object #' . $this->object_id);
        $this->expectFlashData(['error_detected' => ['An error occurred while storing the contribution.']]);

        $this->assertCount(0, $this->getContributions());
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
        $this->assertTrue($rents[0]->isInStock());
        //returned object is held by no one
        $this->assertNull($rents[0]->getAdherentId());
        $this->assertSame('', $rents[0]->getDateEnd());
        $this->assertSame($rents[0]->getId(), $this->getObjectRentId());
        $this->assertNotSame('', $rents[1]->getDateEnd());
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
        $this->assertSame($member_two->id, $rents[0]->getAdherentId());
        $this->assertSame($this->lent_status, $rents[0]->getStatusId());
        $this->assertSame('', $rents[0]->getDateEnd());
        $this->assertSame($rents[0]->getId(), $this->getObjectRentId());
        //comment goes to the closed rent
        $this->assertNotSame('', $rents[1]->getDateEnd());
        $this->assertSame('Handed over', $rents[1]->getComments());

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
            fn(LendRent $rent) => $rent->getId() === $this->getObjectRentId()
        ));
        $this->assertCount(1, $current);
        $this->assertNull($current[0]->getAdherentId());
        $this->assertTrue($current[0]->isInStock());
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

    /**
     * Build a lend page request
     *
     * @param string $action Either take or return
     */
    private function lendPageRequest(string $action): \Slim\Psr7\Request
    {
        return $this->createRequest(
            route_name: 'objectslend_object_take',
            route_args: ['action' => $action, 'id' => (string)$this->object_id]
        );
    }

    /**
     * Take page is displayed for an available object, not for a lent one
     */
    public function testTakePage(): void
    {
        $this->setPrefs(false);
        $this->logSuperAdmin();

        $test_response = $this->app->handle($this->lendPageRequest('take'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('Test object', (string)$test_response->getBody());

        $this->lendObject($this->getMemberOne()->id);
        $test_response = $this->app->handle($this->lendPageRequest('take'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['warning_detected' => ['Test object is currently not available']]);
    }

    /**
     * Take page is refused to members when they cannot borrow
     */
    public function testTakePageRefusedToMember(): void
    {
        $this->setPrefs(false);
        $this->getMemberOne();
        $this->logMember($this->dataAdherentOne());

        $test_response = $this->app->handle($this->lendPageRequest('take'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have rights to borrow objects!']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to borrow an object without appropriate rights!');
    }

    /**
     * Return page is displayed to the holder only
     */
    public function testReturnPage(): void
    {
        $this->setPrefs(true);
        $member_one = $this->getMemberOne();
        $this->lendObject($member_one->id);

        $this->logMember($this->dataAdherentOne());
        $test_response = $this->app->handle($this->lendPageRequest('return'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('Test object', (string)$test_response->getBody());
        $this->login->logout();

        $this->getMemberTwo();
        $this->logMember($this->dataAdherentTwo());
        $test_response = $this->app->handle($this->lendPageRequest('return'));
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectFlashData(['error_detected' => ['You do not have rights to return objects!']]);
        $this->expectLogEntry(Analog::WARNING, 'Trying to return an object without appropriate rights!');
    }
}
