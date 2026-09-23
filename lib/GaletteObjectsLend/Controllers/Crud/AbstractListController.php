<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\Crud;

use DI\Attribute\Inject;
use Galette\Controllers\Crud\AbstractPluginController;
use Galette\Core\Pagination;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Filters\StatusList;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Common code for categories and statuses: list, filter, edit and delete
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TEntity of LendCategory|LendStatus
 * @template TFilters of CategoriesList|StatusList
 */
abstract class AbstractListController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Objects Lend")]
    protected array $module_info;

    /**
     * Routes name part: objectslend_{route}_edit, objectslend_doremove_{route}...
     */
    abstract protected function getEntityRouteName(): string;

    /**
     * Name of the list route
     */
    abstract protected function getListRouteName(): string;

    /**
     * Create empty filters
     *
     * @return TFilters
     */
    abstract protected function createFilters(): Pagination;

    /**
     * Apply posted filters specific to the list
     *
     * @param TFilters            $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyOwnFilters(Pagination $filters, array $post): void
    {
    }

    /**
     * Get list template name and parameters
     *
     * @param TFilters $filters Filters
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    abstract protected function getListView(Pagination $filters): array;

    /**
     * Load an entity
     *
     * @param ?int $id Entity ID, null for a new one
     *
     * @return TEntity
     */
    abstract protected function loadEntity(?int $id): LendCategory|LendStatus;

    /**
     * Fill entity from posted values
     *
     * @param TEntity             $entity Entity
     * @param array<string,mixed> $post   Posted values
     */
    abstract protected function fillEntity(LendCategory|LendStatus $entity, array $post): void;

    /**
     * Get edit template name and parameters
     *
     * @param TEntity $entity Entity
     * @param string  $action Either add or edit
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    abstract protected function getEditView(LendCategory|LendStatus $entity, string $action): array;

    /**
     * Additional work once the entity has been stored
     *
     * @param TEntity             $entity  Entity
     * @param Request             $request PSR Request
     * @param array<string,mixed> $post    Posted values
     *
     * @return array<string> Errors
     */
    protected function afterStore(LendCategory|LendStatus $entity, Request $request, array $post): array
    {
        return [];
    }

    /**
     * Message displayed once the entity has been stored
     */
    abstract protected function getStoredMessage(): string;

    /**
     * Message displayed when the entity cannot be stored
     */
    abstract protected function getStoreErrorMessage(): string;

    /**
     * Get filters from session
     *
     * @return TFilters
     */
    protected function getFilters(): Pagination
    {
        return $this->session->{$this->getFilterName(static::getDefaultFilterName())}
            ?? $this->createFilters();
    }

    /**
     * Store filters in session
     *
     * @param TFilters $filters Filters
     */
    protected function storeFilters(Pagination $filters): void
    {
        $this->session->{$this->getFilterName(static::getDefaultFilterName())} = $filters;
    }

    /**
     * Default filter name, used to store filters in session
     */
    abstract public static function getDefaultFilterName(): string;

    // CRUD - Create

    /**
     * Add page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function add(Request $request, Response $response): Response
    {
        return $this->edit($request, $response, null, 'add');
    }

    /**
     * Add action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->doEdit($request, $response, null, 'add');
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param Request         $request  PSR Request
     * @param Response        $response PSR Response
     * @param string|null     $option   One of 'page' or 'order'
     * @param int|string|null $value    Value of the option
     */
    public function list(Request $request, Response $response, ?string $option = null, int|string|null $value = null): Response
    {
        $filters = $this->getFilters();

        if ($option === 'page') {
            $filters->current_page = (int)$value;
        } elseif ($option === 'order') {
            $filters->orderby = $value;
        }

        [$template, $params] = $this->getListView($filters);

        $this->storeFilters($filters);

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->view->render(
            $response,
            $this->getTemplate($template),
            $params + [
                'require_dialog'    => true,
                'filters'           => $filters
            ]
        );
        return $response;
    }

    /**
     * Filtering
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $filters = $this->getFilters();

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            if (isset($post['filter_str'])) {
                $filters->filter_str = $post['filter_str'];
            }
            if (isset($post['active_filter']) && is_numeric($post['active_filter'])) {
                $filters->active_filter = $post['active_filter'];
            }
            $this->applyOwnFilters($filters, $post);
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
        }

        $this->storeFilters($filters);

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor($this->getListRouteName()));
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int|null $id       Model id
     * @param string   $action   Action
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $entity = $this->loadEntity($id);
        //values posted before an error
        $data_key = $this->getDataSessionKey();
        $data = $this->session->$data_key ?? null;
        if (is_array($data)) {
            $this->fillEntity($entity, $data);
        }
        unset($this->session->$data_key);

        [$template, $params] = $this->getEditView($entity, $action);
        $this->view->render(
            $response,
            $this->getTemplate($template),
            $params + ['action' => $action]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param null|int $id       Model id for edit
     * @param string   $action   Either add or edit
     */
    public function doEdit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $post = $request->getParsedBody();
        $entity = $this->loadEntity($id);
        $this->fillEntity($entity, $post);

        if ($entity->store()) {
            $errors = $this->afterStore($entity, $request, $post);
        } else {
            $errors = [$this->getStoreErrorMessage()];
        }

        if (count($errors) === 0) {
            $this->flash->addMessage('success_detected', $this->getStoredMessage());
            return $response
                ->withStatus(301)
                ->withHeader('Location', $this->routeparser->urlFor($this->getListRouteName()));
        }

        $data_key = $this->getDataSessionKey();
        $this->session->$data_key = $post;
        foreach ($errors as $error) {
            $this->flash->addMessage('error_detected', $error);
        }

        //entity may have been stored before the error
        $route = 'objectslend_' . $this->getEntityRouteName();
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $entity->getId() === null
                    ? $this->routeparser->urlFor($route . '_add')
                    : $this->routeparser->urlFor($route . '_edit', ['id' => (string)$entity->getId()])
            );
    }

    /**
     * Session key for values posted before an error
     */
    private function getDataSessionKey(): string
    {
        return 'objectslend_' . $this->getEntityRouteName() . '_data';
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor($this->getListRouteName());
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'objectslend_doremove_' . $this->getEntityRouteName(),
            $args
        );
    }

    /**
     * Remove entity
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        return $this->loadEntity((int)$args['id'])->delete();
    }

    // /CRUD - Delete
}
