<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\Crud;

use Analog\Analog;
use DI\Attribute\Inject;
use GaletteObjectsLend\Entity\CategoryPicture;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Repository\Categories;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\Preferences;
use Galette\Controllers\Crud\AbstractPluginController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Categories controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class CategoriesController extends AbstractPluginController
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
        if (isset($this->session->objectslend_filter_categories)) {
            $filters = $this->session->objectslend_filter_categories;
        } else {
            $filters = new CategoriesList();
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

        $categories = new Categories($this->zdb, $this->login, $filters);
        $list = $categories->getCategoriesList(true);

        $this->session->objectslend_filter_categories = $filters;

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $lendsprefs = new Preferences($this->zdb);
        // display page
        $this->view->render(
            $response,
            $this->getTemplate('categories_list'),
            [
                'page_title'            => _T("Categories list", "objectslend"),
                'require_dialog'        => true,
                'categories'            => $list,
                'nb_categories'         => count($list),
                'filters'               => $filters,
                'olendsprefs'           => $lendsprefs,
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
        if (isset($this->session->objectslend_filter_categories)) {
            $filters = $this->session->objectslend_filter_categories;
        } else {
            $filters = new CategoriesList();
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
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
        }

        $this->session->objectslend_filter_categories = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('objectslend_categories'));
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
        if ($this->session->objectslend_category !== null) {
            $category = $this->session->objectslend_category;
            $this->session->objectslend_category = null;
        } else {
            $category = new LendCategory($this->zdb, $id);
        }

        if ($category->getId() !== null) {
            $title = _T("Edit category", "objectslend");
        } else {
            $title = _T("New category", "objectslend");
        }

        $lendsprefs = new Preferences($this->zdb);
        $picture = new CategoryPicture($category->getId());
        $params = [
            'page_title'    => $title,
            'category'      => $category,
            'time'          => time(),
            'action'        => $action,
            'olendsprefs'   => $lendsprefs,
            'picture'       => $picture
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('category_edit'),
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
        $category = new LendCategory($this->zdb, $id);
        $error_detected = [];

        $category
            ->setName($post['name'])
            ->setActive(($post['is_active'] ?? false) == true);
        if ($category->store()) {
            // picture upload
            $picture = new CategoryPicture($category->getId());
            if (!$picture->upload($request->getUploadedFiles(), 'picture')) {
                $error_detected = $picture->uploadErrors();
            }

            if (isset($post['del_photo'])) {
                if (!$picture->delete()) {
                    $error_detected[] = _T("Delete failed", "objectslend");
                    Analog::log(
                        'Unable to delete picture for category ' . $category->getName(false),
                        Analog::ERROR
                    );
                }
            }
        } else {
            $error_detected[] = _T("An error occurred while storing the category.", "objectslend");
        }

        $args = ($id === null ? [] : ['id' => $id]);
        if (count($error_detected)) {
            $this->session->objectslend_category = $category;
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_category_' . $action, $args)
                );
        } else {
            //redirect to categories list
            $this->flash->addMessage(
                'success_detected',
                _T("Category has been saved", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_categories', $args)
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
        return $this->routeparser->urlFor('objectslend_categories');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'objectslend_doremove_category',
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
        $category = new LendCategory($this->zdb, (int)$args['id']);
        return sprintf(
            _T('Remove category %1$s', 'objectslend'),
            $category->getName(false)
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
        $category = new LendCategory($this->zdb, (int)$args['id']);
        return $category->delete();
    }

    // /CRUD - Delete
    // /CRUD
}
