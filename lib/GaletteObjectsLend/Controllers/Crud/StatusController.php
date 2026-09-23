<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\Crud;

use DI\Attribute\Inject;
use GaletteObjectsLend\Filters\StatusList;
use GaletteObjectsLend\Repository\Status;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Entity\Preferences;
use Galette\Controllers\Crud\AbstractPluginController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Status controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class StatusController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Objects Lend")]
    protected array $module_info;

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
        if (isset($this->session->objectslend_filter_statuses)) {
            $filters = $this->session->objectslend_filter_statuses;
        } else {
            $filters = new StatusList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
            }
        }

        $statuses = new Status($this->zdb, $this->login, $filters);
        $list = $statuses->getStatusList(true);

        if (count($statuses->getActiveStockStatuses()) == 0) {
            $this->flash->addMessage(
                'error_detected',
                _T("Please add at last one status \"in stock\"!", "objectslend")
            );
        }
        if (count($statuses->getActiveTakeAwayStatuses()) == 0) {
            $this->flash->addMessage(
                'error_detected',
                _T("Please add at least one status \"object borrowed\"!", "objectslend")
            );
        }

        $this->session->objectslend_filter_statuses = $filters;

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $lendsprefs = new Preferences($this->zdb);
        // display page
        $this->view->render(
            $response,
            $this->getTemplate('status_list'),
            [
                'page_title'            => _T("Status list", "objectslend"),
                'require_dialog'        => true,
                'statuses'              => $list,
                'nb_status'             => count($list),
                'olendsprefs'           => $lendsprefs,
                'filters'               => $filters,
                'time'                  => time()
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
        if (isset($this->session->objectslend_filter_statuses)) {
            $filters = $this->session->objectslend_filter_statuses;
        } else {
            $filters = new StatusList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //string to filter
            if (isset($post['filter_str'])) { //filter search string
                $filters->filter_str = stripslashes(
                    htmlspecialchars($post['filter_str'], ENT_QUOTES)
                );
            }
            //activity to filter
            if (isset($post['active_filter'])) {
                if (is_numeric($post['active_filter'])) {
                    $filters->active_filter = $post['active_filter'];
                }
            }
            //stock to filter
            if (isset($post['stock_filter'])) {
                if (is_numeric($post['stock_filter'])) {
                    $filters->stock_filter = $post['stock_filter'];
                }
            }

            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
        }

        $this->session->objectslend_filter_statuses = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('objectslend_statuses'));
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
        if ($this->session->objectslend_status !== null) {
            $status = $this->session->objectslend_status;
            $this->session->objectslend_status = null;
        } else {
            $status = new LendStatus($this->zdb, $id);
        }

        if ($status->getId() !== null) {
            $title = str_replace(
                '%status',
                $status->getText(),
                _T("Edit status %status", "objectslend")
            );
        } else {
            $title = _T("New status", "objectslend");
        }

        $params = [
            'page_title'    => $title,
            'status'        => $status,
            'action'        => $action
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('status_edit'),
            $params
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
        $status = new LendStatus($this->zdb, $id);
        $error_detected = [];

        $days = trim($post['rent_day_number']);
        $status
            ->setText($post['text'])
            ->setInStock(isset($post['in_stock']))
            ->setActive(isset($post['is_active']))
            ->setRentDayNumber(strlen($days) > 0 ? (int)$days : null);
        if (!$status->store()) {
            $error_detected[] = _T("An error occurred while storing the status.", "objectslend");
        }

        if (count($error_detected)) {
            $this->session->objectslend_status = $status;
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }

            $args = ($action == 'edit' ? ['id' => $status->getId()] : []);
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'objectslend_status_' . $action,
                        $args
                    )
                );
        } else {
            //redirect to status list
            $this->flash->addMessage(
                'success_detected',
                _T("Status has been saved", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_statuses')
                );
        }
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
        return $this->routeparser->urlFor('objectslend_statuses');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'objectslend_doremove_status',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        $status = new LendStatus($this->zdb, (int)$args['id']);
        return sprintf(
            _T('Remove status %1$s', 'objectslend'),
            $status->getText()
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $status = new LendStatus($this->zdb, (int)$args['id']);
        return $status->delete();
    }

    // /CRUD - Delete
    // /CRUD
}
