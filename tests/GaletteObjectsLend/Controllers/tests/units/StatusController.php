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
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Repository\Status;

/**
 * Status controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class StatusController extends GaletteRoutingTestCase
{
    protected int $seed = 20260923101542;
    protected bool $load_plugins = true;
    //a failing query aborts the whole transaction on PostgreSQL
    protected bool $db_transactions = false;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        //installation scripts add example statuses
        $this->zdb->execute($this->zdb->delete(LEND_PREFIX . LendStatus::TABLE));
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->zdb->execute($this->zdb->delete(LEND_PREFIX . LendStatus::TABLE));
        parent::tearDown();
    }

    /**
     * Store a status
     *
     * @param string $text     Text
     * @param bool   $in_stock In stock
     */
    private function addStatus(string $text, bool $in_stock): LendStatus
    {
        $status = new LendStatus($this->zdb);
        $status->setText($text)->setInStock($in_stock)->setActive(true);
        $status->store();
        return $status;
    }

    /**
     * List warns when statuses are missing
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $this->addStatus('Shelf <A>', true);

        $test_response = $this->app->handle($this->createRequest(route_name: 'objectslend_statuses'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('Shelf &lt;A&gt;', (string)$test_response->getBody());
        $this->expectFlashData(['error_detected' => ['Please add at least one status "object borrowed"!']]);
    }

    /**
     * Filters are stored in session, search string as is
     */
    public function testFilter(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(route_name: 'objectslend_filter_statuses', method: 'POST')
            ->withParsedBody([
                'filter_str' => '<A> & B',
                'active_filter' => (string)Status::ACTIVE,
                'stock_filter' => (string)Status::IN_STOCK
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            [$this->routeparser->urlFor('objectslend_statuses')],
            $test_response->getHeader('Location')
        );

        $filters = $this->session->plugin_objectslend_statuses_filter;
        $this->assertSame('<A> & B', $filters->filter_str);
        $this->assertSame(Status::ACTIVE, $filters->active_filter);
        $this->assertSame(Status::IN_STOCK, $filters->stock_filter);

        //search string is found
        $this->addStatus('Shelf <A> & B', true);
        $this->addStatus('Shelf C', true);
        $statuses = new Status($this->zdb, $this->preferences, $this->login, $filters);
        $this->assertCount(1, $statuses->getStatusList(true));

        $request = $this->createRequest(route_name: 'objectslend_filter_statuses', method: 'POST')
            ->withParsedBody(['clear_filter' => '1']);
        $this->app->handle($request);
        $this->assertNull($this->session->plugin_objectslend_statuses_filter->filter_str);
    }

    /**
     * Add and edit a status
     */
    public function testAddAndEdit(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(route_name: 'objectslend_status_action_add', method: 'POST')
            ->withParsedBody([
                'text' => 'Garage',
                'in_stock' => '1',
                'is_active' => '1',
                'rent_day_number' => '7'
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            [$this->routeparser->urlFor('objectslend_statuses')],
            $test_response->getHeader('Location')
        );
        $this->expectFlashData(['success_detected' => ['Status has been saved']]);

        $list = (new Status($this->zdb, $this->preferences, $this->login))->getList(true);
        $this->assertCount(1, $list);
        $status = $list[0];
        $this->assertSame('Garage', $status->getText());
        $this->assertTrue($status->isInStock());
        $this->assertSame(7, $status->getRentDayNumber());

        $test_response = $this->app->handle(
            $this->createRequest(route_name: 'objectslend_status_edit', route_args: ['id' => (string)$status->getId()])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('value="Garage"', (string)$test_response->getBody());

        $request = $this->createRequest(
            route_name: 'objectslend_status_action_edit',
            route_args: ['id' => (string)$status->getId()],
            method: 'POST'
        )->withParsedBody(['text' => 'Garage (edited)', 'rent_day_number' => '']);
        $this->app->handle($request);
        $this->expectFlashData(['success_detected' => ['Status has been saved']]);

        $status = new LendStatus($this->zdb, $status->getId());
        $this->assertSame('Garage (edited)', $status->getText());
        $this->assertFalse($status->isInStock());
        $this->assertFalse($status->isActive());
        $this->assertNull($status->getRentDayNumber());
    }

    /**
     * A status that cannot be stored brings the form back with posted values
     */
    public function testStoreError(): void
    {
        $this->logSuperAdmin();
        $text = str_repeat('x', 150);
        $request = $this->createRequest(route_name: 'objectslend_status_action_add', method: 'POST')
            ->withParsedBody(['text' => $text, 'rent_day_number' => '']);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            [$this->routeparser->urlFor('objectslend_status_add')],
            $test_response->getHeader('Location')
        );
        $this->expectFlashData(['error_detected' => ['An error occurred while storing the status.']]);
        $this->expectLogEntry(Analog::ERROR, 'Unable to store status #new');
        $this->expectLogEntry(Analog::ERROR, 'Query error');
        $this->assertSame($text, $this->session->objectslend_status_data['text']);
        $this->assertCount(0, (new Status($this->zdb, $this->preferences, $this->login))->getList(true));
    }

    /**
     * Remove a status
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $status = $this->addStatus('To be removed', true);

        $test_response = $this->app->handle(
            $this->createRequest(route_name: 'objectslend_remove_status', route_args: ['id' => (string)$status->getId()])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('Remove status To be removed', (string)$test_response->getBody());

        $request = $this->createRequest(
            route_name: 'objectslend_doremove_status',
            route_args: ['id' => (string)$status->getId()],
            method: 'POST'
        )->withParsedBody(['confirm' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertNull((new LendStatus($this->zdb, $status->getId()))->getId());
    }
}
