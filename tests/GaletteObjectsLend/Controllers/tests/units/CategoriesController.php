<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Repository\Categories;

/**
 * Categories controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CategoriesController extends GaletteRoutingTestCase
{
    protected int $seed = 20260923102218;
    protected bool $load_plugins = true;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->zdb->execute($this->zdb->delete(LEND_PREFIX . LendCategory::TABLE));
        parent::tearDown();
    }

    /**
     * Store a category
     *
     * @param string $name Name
     */
    private function addCategory(string $name): LendCategory
    {
        $category = new LendCategory($this->zdb);
        $category->setName($name)->setActive(true);
        $category->store();
        return $category;
    }

    /**
     * List and filter categories
     */
    public function testListAndFilter(): void
    {
        $this->logSuperAdmin();
        $this->addCategory('Tools <b>');
        $this->addCategory('Books');

        $test_response = $this->app->handle($this->createRequest(route_name: 'objectslend_categories'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Tools &lt;b&gt;', $body);
        $this->assertStringContainsString('Books', $body);

        $request = $this->createRequest(route_name: 'objectslend_filter_categories', method: 'POST')
            ->withParsedBody(['filter_str' => '<b>', 'active_filter' => (string)Categories::ACTIVE]);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());

        $filters = $this->session->plugin_objectslend_categories_filter;
        $this->assertSame('<b>', $filters->filter_str);
        $categories = new Categories($this->zdb, $this->preferences, $this->login, $filters);
        $this->assertCount(1, $categories->getCategoriesList(true));

        $test_response = $this->app->handle($this->createRequest(route_name: 'objectslend_categories'));
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Tools &lt;b&gt;', $body);
        $this->assertStringNotContainsString('Books', $body);
    }

    /**
     * Add and edit a category
     */
    public function testAddAndEdit(): void
    {
        $this->logSuperAdmin();
        $test_response = $this->app->handle($this->createRequest(route_name: 'objectslend_category_add'));
        $this->assertSame(200, $test_response->getStatusCode());

        $request = $this->createRequest(route_name: 'objectslend_category_action_add', method: 'POST')
            ->withParsedBody(['name' => 'Tools', 'is_active' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertSame(
            [$this->routeparser->urlFor('objectslend_categories')],
            $test_response->getHeader('Location')
        );
        $this->expectFlashData(['success_detected' => ['Category has been saved']]);

        $list = (new Categories($this->zdb, $this->preferences, $this->login))->getList(true);
        $this->assertCount(1, $list);
        $category = $list[0];
        $this->assertSame('Tools', $category->getName(false));
        $this->assertTrue($category->isActive());

        $test_response = $this->app->handle(
            $this->createRequest(route_name: 'objectslend_category_edit', route_args: ['id' => (string)$category->getId()])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('value="Tools"', (string)$test_response->getBody());

        $request = $this->createRequest(
            route_name: 'objectslend_category_action_edit',
            route_args: ['id' => (string)$category->getId()],
            method: 'POST'
        )->withParsedBody(['name' => 'Tools (edited)']);
        $this->app->handle($request);
        $this->expectFlashData(['success_detected' => ['Category has been saved']]);

        $category = new LendCategory($this->zdb, $category->getId());
        $this->assertSame('Tools (edited)', $category->getName(false));
        $this->assertFalse($category->isActive());
    }

    /**
     * Remove a category
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $category = $this->addCategory('To be removed');

        $test_response = $this->app->handle(
            $this->createRequest(route_name: 'objectslend_remove_category', route_args: ['id' => (string)$category->getId()])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('Remove category To be removed', (string)$test_response->getBody());

        $request = $this->createRequest(
            route_name: 'objectslend_doremove_category',
            route_args: ['id' => (string)$category->getId()],
            method: 'POST'
        )->withParsedBody(['confirm' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(301, $test_response->getStatusCode());
        $this->assertNull((new LendCategory($this->zdb, $category->getId()))->getId());
    }
}
