<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\LendException;

/**
 * Lend service tests
 *
 * Tests run outside a transaction, so the service ones are real.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LendService extends GaletteTestCase
{
    protected int $seed = 20260923081245;
    protected bool $db_transactions = false;
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

        $this->orig_prefs = [];
        foreach (array_keys(LendPreferences::getSchema()) as $name) {
            $this->orig_prefs[$name] = $this->preferences->getPluginValue($name);
        }
        $values = [
            LendPreferences::ENABLE_MEMBER_RENT_OBJECT => 1,
            LendPreferences::AUTO_GENERATE_CONTRIBUTION => 1,
            LendPreferences::GENERATED_CONTRIBUTION_TYPE_ID => 5,
            LendPreferences::GENERATED_CONTRIB_INFO_TEXT => 'Service rent of {NAME}',
        ];
        foreach ($values as $name => $value) {
            $this->assertTrue($this->preferences->setValue($name, $value, $this->login));
        }

        $status = new LendStatus($this->zdb);
        $status->setText('In stock');
        $status->setInStock(true);
        $status->setActive(true);
        $status->store();
        $this->instock_status = $status->getId();

        $status = new LendStatus($this->zdb);
        $status->setText('Lent');
        $status->setInStock(false);
        $status->setActive(true);
        $status->store();
        $this->lent_status = $status->getId();

        $object = new LendObject($this->zdb);
        $object->setName('Service object');
        $object->setRentPrice(3.5);
        $object->store();
        $this->object_id = $object->getId();
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();

        foreach ($this->orig_prefs as $name => $value) {
            $this->preferences->setValue($name, $value, $this->login);
        }

        $update = $this->zdb->update(LEND_PREFIX . LendObject::TABLE)
            ->set([LendRent::PK => null]);
        $this->zdb->execute($update);

        $delete = $this->zdb->delete(LEND_PREFIX . LendRent::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . LendObject::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . LendStatus::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where->like('info_cotis', 'Service rent of %');
        $this->zdb->execute($delete);
        $this->cleanMembers();

        parent::tearDown();
    }

    /**
     * Get service instance
     */
    private function getService(): \GaletteObjectsLend\LendService
    {
        return new \GaletteObjectsLend\LendService($this->zdb, $this->preferences, $this->login, new LendPreferences($this->preferences));
    }

    /**
     * Count stored contributions from this test
     */
    private function countContributions(): int
    {
        $select = $this->zdb->select(\Galette\Entity\Contribution::TABLE);
        $select->where->like('info_cotis', 'Service rent of %');
        return $this->zdb->execute($select)->count();
    }

    /**
     * Test take stores rent, current rent and contribution
     */
    public function testTake(): void
    {
        $member = $this->getMemberOne();
        $this->logSuperAdmin();
        $service = $this->getService();

        $contribution = $service->take(
            $service->getObject($this->object_id),
            $this->lent_status,
            member_id: $member->id
        );
        $this->assertInstanceOf(\Galette\Entity\Contribution::class, $contribution);
        $this->assertSame(1, $this->countContributions());

        $object = $service->getObject($this->object_id);
        $this->assertTrue($service->isLent($object));
        $this->assertFalse($service->isAvailable($object));
        $this->assertSame($member->id, $object->getIdAdh());
    }

    /**
     * Test nothing is stored when contribution cannot be
     */
    public function testTakeRollsBackOnContributionError(): void
    {
        $member = $this->getMemberOne();
        $this->logSuperAdmin();
        $service = $this->getService();

        try {
            $service->take(
                $service->getObject($this->object_id),
                $this->lent_status,
                member_id: $member->id,
                payment_type: 999999
            );
            $this->fail('Take should have failed');
        } catch (LendException $e) {
            $this->assertSame('An error occurred while storing the contribution.', $e->getMessage());
        }
        $this->expectLogEntry(Analog::WARNING, 'Unknown payment type 999999');
        $this->expectLogEntry(Analog::ERROR, 'Some errors has been threw attempting to edit/store a contribution');
        $this->expectLogEntry(Analog::ERROR, 'Unable to generate contribution for object #' . $this->object_id);

        $this->assertSame(0, $this->countContributions());
        $this->assertCount(0, (new \GaletteObjectsLend\Repository\Rents($this->zdb))->getForObject($this->object_id));
        $object = $service->getObject($this->object_id);
        $this->assertNull($object->getRentId());
        $this->assertTrue($service->isAvailable($object));
    }

    /**
     * Test status change refuses unknown and inactive statuses
     */
    public function testChangeStatusInvalid(): void
    {
        $status = new LendStatus($this->zdb);
        $status->setText('Inactive');
        $status->setInStock(true);
        $status->setActive(false);
        $status->store();

        $this->logSuperAdmin();
        $service = $this->getService();
        $object = $service->getObject($this->object_id);

        foreach ([$status->getId(), 999999] as $status_id) {
            try {
                $service->changeStatus($object, (int)$status_id);
                $this->fail('Status change should have failed');
            } catch (LendException $e) {
                $this->assertSame('This status cannot be set.', $e->getMessage());
            }
            $this->expectLogEntry(Analog::WARNING, 'Trying to change an object status to an invalid one!');
        }

        $service->changeStatus($object, $this->instock_status);
        $object = $service->getObject($this->object_id);
        $this->assertNotNull($object->getRentId());
        $this->assertTrue($service->isAvailable($object));
    }
}
