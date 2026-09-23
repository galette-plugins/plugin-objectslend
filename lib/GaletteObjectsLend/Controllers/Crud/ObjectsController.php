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
use GaletteObjectsLend\Entity\ObjectPicture;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Filters\ObjectsList;
use GaletteObjectsLend\Filters\StatusList;
use GaletteObjectsLend\Repository\Categories;
use GaletteObjectsLend\Repository\Objects;
use GaletteObjectsLend\Repository\Status;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Entity\Preferences;
use Galette\Controllers\Crud\AbstractPluginController;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\ContributionsTypes;
use Galette\Repository\Members;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Objects controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ObjectsController extends AbstractPluginController
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
        if (isset($this->session->objectslend_filter_objects)) {
            $filters = $this->session->objectslend_filter_objects;
        } else {
            $filters = new ObjectsList();
        }

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'category':
                    if ($value == 0) {
                        $value = null;
                    }
                    $filters->category_filter = $value;
                    break;
            }
        }

        $lendsprefs = new Preferences($this->zdb);
        $objects = new Objects($this->zdb, $lendsprefs, $filters);
        $list = $objects->getObjectsList(true);

        $this->session->objectslend_filter_objects = $filters;

        //assign pagination variables to the template and add pagination links
        $filters->setViewCommonsFilters($lendsprefs, $this->view);
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $cat_filters = new CategoriesList();
        $cat_filters->active_filter = Categories::ACTIVE_CATEGORIES; //retrieve only active categories
        $cat_filters->not_empty = true; //retrieve only categories with objects
        $cat_filters->setObjectsFilter($filters); //search for categories corresponding to filtered objects
        $categories = new Categories($this->zdb, $this->login, $cat_filters);
        $categories_list = $categories->getCategoriesList(true, null, false);

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('objects_list'),
            [
                'page_title' => _T("Objects list", "objectslend"),
                'require_dialog' => true,
                'objects' => $list,
                'nb_objects' => count($list),
                'filters' => $filters,
                'lendsprefs' => $lendsprefs->getPreferences(),
                'olendsprefs' => $lendsprefs,
                'time' => time(),
                'module_id' => $this->getModuleId(),
                'categories' => $categories_list
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
        if (isset($this->session->objectslend_filter_objects)) {
            $filters = $this->session->objectslend_filter_objects;
        } else {
            $filters = new ObjectsList();
        }

        //reintialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //string to filter
            if (isset($post['filter_str'])) { //filter search string
                $filters->filter_str = $post['filter_str'];
            }
            //field to search into
            if (isset($post['field_filter'])) {
                $filters->field_filter = $post['field_filter'];
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

        $this->session->objectslend_filter_objects = $filters;

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('objectslend_objects'));
    }

    /**
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Object id
     */
    public function show(Request $request, Response $response, int $id): Response
    {
        $lendsprefs = new Preferences($this->zdb);

        $deps = [
            'picture'   => true,
            'rents'     => true,
            'status'    => true,
            'member'    => true,
            'category'  => $lendsprefs->{Preferences::PARAM_VIEW_CATEGORY}
        ];
        $object = new LendObject(
            $this->zdb,
            $id,
            $deps
        );

        $params = [
            'page_title' => str_replace('%object', $object->name, _T('Rents list for %object', 'objectslend')),
            'object' => $object,
            'rents' => $object->rents,
            'time' => time(),
            'ajax' => $this->isAjax($request)
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('list_lent_object'),
            $params
        );
        return $response;
    }

    /**
     * Batch actions handler
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function handleBatch(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        if (isset($post['entries_sel'])) {
            if (isset($this->session->objectslend_filter_objects)) {
                $filters = $this->session->objectslend_filter_objects;
            } else {
                $filters = new ObjectsList();
            }

            $filters->selected = $post['entries_sel'];
            $this->session->objectslend_filter_objects = $filters;

            if (isset($post['delete'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('objectslend_remove_objects'));
            }

            if (isset($post['print_list'])) {
                return $response
                    ->withStatus(301)
                    ->withHeader('Location', $this->routeparser->urlFor('objectslend_objects_print'));
            }

            throw new \RuntimeException('Does not know what to batch :(');
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T("No object was selected, please check at least one.", "objectslend")
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('objectslend_objects'));
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int|null $id       Object id
     * @param string   $action   Action
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        if ($this->session->objectslend_object !== null) {
            $object = $this->session->objectslend_object;
            $this->session->objectslend_object = null;
        } else {
            $deps = ['rents' => true];
            $object = new LendObject($this->zdb, $id, $deps);
        }

        $categories = new Categories($this->zdb, $this->login);
        $categories_list = $categories->getCategoriesList(true);

        if ($object->object_id !== null) {
            $title = _T("Edit object", "objectslend");
        } else {
            $title = _T("New object", "objectslend");
        }

        $sfilter = new StatusList();
        $sfilter->active_filter = \GaletteObjectsLend\Repository\Status::ACTIVE;
        $statuses = new Status($this->zdb, $this->login, $sfilter);
        $slist = $statuses->getStatusList(true);

        $lendsprefs = new Preferences($this->zdb);
        $picture = new ObjectPicture($object->object_id);
        $params = [
            'page_title'    => $title,
            'object'        => $object,
            'time'          => time(),
            'action'        => $action,
            'lendsprefs'    => $lendsprefs->getPreferences(),
            'olendsprefs'   => $lendsprefs,
            'categories'    => $categories_list,
            'statuses'      => $slist,
            'picture'       => $picture
        ];

        // members
        $m = new Members();
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            ($this->login->isSuperadmin() ? null : $this->login->id)
        );

        $params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        if (count($members)) {
            $params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('objects_edit'),
            $params
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param null|int $id       Object id for edit
     * @param string   $action   Either add or edit
     */
    public function doEdit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $post = $request->getParsedBody();

        $object = new LendObject($this->zdb, $id);
        $error_detected = [];

        $object->name = $post['name'];
        $object->description = $post['description'];
        //TODO: check if category do exits?
        $object->category_id = empty($post['category_id']) ? null : $post['category_id'];
        $object->serial_number = $post['serial'];
        if ($post['price'] != '') {
            //FIXME: better currency format handler
            $object->price = (float)str_replace(' ', '', str_replace(',', '.', $post['price']));
        }
        if ($post['rent_price'] != '') {
            //FIXME: better currency format handler
            $object->rent_price = (float)str_replace(' ', '', str_replace(',', '.', $post['rent_price']));
        }
        $object->price_per_day = ($post['price_per_day'] ?? false) == true;
        $object->dimension = $post['dimension'];
        if ($post['weight'] != '') {
            //FIXME: better format handler
            $object->weight = (float)str_replace(' ', '', str_replace(',', '.', $post['weight']));
        }
        $object->is_active = ($post['is_active'] ?? false) == true;

        if ($object->store()) {
            if (!empty($post['1st_status'])) {
                $rent = new LendRent();
                $rent->object_id = $object->getId();
                $rent->status_id = (int)$post['1st_status'];
                $rent->store();
            }

            // picture upload
            if (!$object->picture->upload($request->getUploadedFiles(), 'picture')) {
                $error_detected = $object->picture->uploadErrors();
            }

            if (isset($post['del_picture'])) {
                if (!$object->picture->delete()) {
                    $error_detected[] = _T("Delete failed", "objectslend");
                    Analog::log(
                        'Unable to delete picture for object ' . $object->name,
                        Analog::ERROR
                    );
                }
            }
        } else {
            $error_detected[] = _T("Something went wrong saving object :(", "objectslend");
        }

        if (count($error_detected)) {
            $this->session->objectslend_object = $object;
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }

            $args = ($action == 'add' ? [] : ['id' => $object->object_id]);
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        'objectslend_object_' . $action,
                        $args
                    )
                );
        } else {
            //redirect to objects list
            $this->flash->addMessage(
                'success_detected',
                _T("Object has been saved", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }
    }

    /**
     * Update status action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param null|int $id       Object id for edit
     * @param string   $action   Either add or edit
     */
    public function doUpdateStatus(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $post = $request->getParsedBody();

        $object = new LendObject($this->zdb, $id);

        LendRent::closeAllRentsForObject($object->getId(), $post['new_comment']);

        $rent = new LendRent();
        $rent->object_id = $object->getId();
        $rent->status_id = $post['new_status'];
        if (!empty($post['new_adh'])) {
            $rent->adherent_id = (int)$post['new_adh'];
        }
        $rent->store();

        //redirect to objects form
        $this->flash->addMessage(
            'success_detected',
            _T("Status has been updated", "objectslend")
        );

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('objectslend_object_edit', ['id' => $object->getId()])
            );
    }

    /**
     * Clone confirmation page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Object id to clone
     */
    public function confirmClone(Request $request, Response $response, int $id): Response
    {
        $object = new LendObject($this->zdb, $id);

        $this->view->render(
            $response,
            $this->getTemplate('clone_object'),
            [
                'page_title' => _T('Duplicate object', 'objectslend'),
                'object' => $object
            ]
        );
        return $response;
    }

    /**
     * Clone action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Object id for edit
     */
    public function doClone(Request $request, Response $response, int $id): Response
    {
        $object = new LendObject($this->zdb, $id);

        if ($object->clone()) {
            $this->flash->addMessage(
                'success_detected',
                str_replace(
                    '%id',
                    (string)$id,
                    _T('Successfully cloned from #%id.<br/>You can now edit it.', 'objectslend')
                )
            );
        } else {
            $this->flash->addMessage(
                'error_detected',
                _T('An error occurred cloning object :(', 'objectslend')
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'objectslend_object_edit',
                    ['id' => $object->object_id]
                )
            );
    }

    /**
     * Objects lends lend page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $action   Action (either 'take' or 'return')
     * @param int      $id       Objects ID
     */
    public function lend(Request $request, Response $response, string $action, int $id): Response
    {
        $lendsprefs = new Preferences($this->zdb);

        $params = [
            'page_title'    => (
                $action == 'take'
                ? _T("Borrow an object", "objectslend")
                : _T("Return a borrowed object", "objectslend")
            ),
            'time'          => time(),
            'statuses'      => ($action == 'take'
                ? LendStatus::getActiveTakeAwayStatuses($this->zdb)
                : LendStatus::getActiveStockStatuses($this->zdb)),
            'lendsprefs'    => $lendsprefs->getPreferences(),
            'olendsprefs'   => $lendsprefs,
            'ajax'          => $this->isAjax($request),
            'takeorgive'    => $action,
            'adh_selected'  => ($this->login->isSuperadmin() ? null : $this->login->id),
            'contribution'  => new Contribution($this->zdb, $this->login)

        ];

        $deps = [
            'rents'     => true,
            'last_rent' => true,
            'member'    => true,
            'category'  => true
        ];
        $object = new LendObject(
            $this->zdb,
            $id,
            $deps
        );
        $params['object'] = $object;
        $last_rent = $object->rents[0] ?? null;
        $params['last_rent'] = $last_rent;

        if ($action == 'take') {
            if (
                !$lendsprefs->{Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT}
                && !($this->login->isAdmin() || $this->login->isStaff())
            ) {
                Analog::log(
                    'Trying to borrow an object without appropriate rights! (Object '
                    . $id . ', user ' . $this->login->login . ')',
                    Analog::WARNING
                );

                //redirect to objects list
                $this->flash->addMessage(
                    'error_detected',
                    _T("You do not have rights to borrow objects!", "objectslend")
                );

                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('objectslend_objects')
                    );
            }

            // members
            $m = new Members();
            $members = $m->getDropdownMembers(
                $this->zdb,
                $this->login,
                ($this->login->isSuperadmin() ? null : $this->login->id)
            );

            $params['members'] = [
                'filters'   => $m->getFilters(),
                'count'     => $m->getCount()
            ];

            if (count($members)) {
                $params['members']['list'] = $members;
            }
            $params['require_calendar'] = true;
            $params['rent_price'] = str_replace([ ',', ' '], [ '.', ''], $object->rent_price); //FIXME :/

            if (!$object->isActive() || ($last_rent !== null && !$last_rent->in_stock)) {
                //redirect to objects list
                $this->flash->addMessage(
                    'warning_detected',
                    str_replace(
                        '%object',
                        $object->name,
                        _T("%object is currently not available", "objectslend")
                    )
                );

                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('objectslend_objects')
                    );
            }

            $date_forecast = new \DateTime();
            $date_forecast->add(new \DateInterval('P1D'));
            $params['date_forecast'] = $date_forecast->format(__('Y-m-d'));
        } else {
            if (
                !$this->canGiveBack($object, $lendsprefs)
            ) {
                Analog::log(
                    'Trying to return an object without appropriate rights! (Object '
                    . $id . ', user ' . $this->login->login . ')',
                    Analog::WARNING
                );

                //redirect to objects list
                $this->flash->addMessage(
                    'error_detected',
                    _T("You do not have rights to return objects!", "objectslend")
                );

                return $response
                    ->withStatus(301)
                    ->withHeader(
                        'Location',
                        $this->routeparser->urlFor('objectslend_objects')
                    );
            }
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('take_object'),
            $params
        );
        return $response;
    }

    /**
     * Objects lends take action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Objects ID
     */
    public function doTake(Request $request, Response $response, int $id): Response
    {
        $lendsprefs = new Preferences($this->zdb);
        $post = $request->getParsedBody();

        $object_id = $id;

        if (
            !$lendsprefs->{Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT}
            && !($this->login->isAdmin() || $this->login->isStaff())
        ) {
            Analog::log(
                'Trying to borrow an object without appropriate rights! (Object '
                . $id . ', user ' . $this->login->login . ')',
                Analog::WARNING
            );

            //redirect to objects list
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have rights to borrow objects!", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }

        $object = new LendObject(
            $this->zdb,
            $object_id,
            ['rents' => true, 'last_rent' => true, 'category' => true]
        );
        $last_rent = $object->getCurrentRent();
        if (
            $object->getId() === null
            || !$object->isActive()
            || ($last_rent !== null && !$last_rent->in_stock)
            || !$this->isAllowedStatus((int)($post['status'] ?? 0), LendStatus::getActiveTakeAwayStatuses($this->zdb))
        ) {
            Analog::log(
                'Trying to borrow an unavailable object or with an invalid status! (Object '
                . $id . ', user ' . $this->login->login . ')',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("This object cannot be borrowed.", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }

        // close olds object rents
        LendRent::closeAllRentsForObject($object_id, '');

        // Ajout d'un nouveau statut "objet loué"
        $rent = new LendRent();
        $rent->object_id = $object_id;
        $rent->status_id = (int)$post['status'];
        $rent->date_forecast = $post['expected_return'];

        if (!empty($post[Adherent::PK]) && ($this->login->isAdmin() || $this->login->isStaff())) {
            $rent->adherent_id = (int)$post[Adherent::PK];
        } else {
            $rent->adherent_id = $this->login->id;
        }
        $rent->store();

        //retrieve object information
        $object = new LendObject(
            $this->zdb,
            $object_id
        );

        // Add contribution
        if ($lendsprefs->{Preferences::PARAM_AUTO_GENERATE_CONTRIBUTION}) {
            //retrieve lend price
            $rentprice = $object->value_rent_price;
            if ($post['rent_price']  && ($this->login->isAdmin() || $this->login->isStaff())) {
                $rentprice = floatval(str_replace(' ', '', str_replace(',', '.', $post['rent_price'])));
            }

            if ($rentprice > 0) {
                $contrib = new Contribution($this->zdb, $this->login);

                $info = str_replace(
                    [
                        '{NAME}',
                        '{DESCRIPTION}',
                        '{SERIAL_NUMBER}',
                        '{PRICE}',
                        '{RENT_PRICE}',
                        '{WEIGHT}',
                        '{DIMENSION}'
                    ],
                    [
                        $object->name,
                        $object->description,
                        $object->serial_number,
                        $object->price,
                        $object->rent_price,
                        $object->weight,
                        $object->dimension
                    ],
                    $lendsprefs->{Preferences::PARAM_GENERATED_CONTRIB_INFO_TEXT}
                );

                $values = [
                    'montant_cotis'         => $rentprice,
                    ContributionsTypes::PK  => $lendsprefs->{Preferences::PARAM_GENERATED_CONTRIBUTION_TYPE_ID},
                    'date_enreg'            => date("Y-m-d"),
                    'date_debut_cotis'      => date("Y-m-d"),
                    'type_paiement_cotis'   => $post['payment_type'],
                    'info_cotis'            => $info,
                    Adherent::PK            => $rent->adherent_id
                ];
                $contrib->check($values, [], []);
                try {
                    $created = $contrib->store();
                } catch (\OverflowException $e) {
                    $created = false;
                    Analog::log(
                        $e->getMessage(),
                        Analog::ERROR
                    );
                }
                if ($created) {
                    $this->flash->addMessage(
                        'success_detected',
                        _T('Contribution has been successfully stored')
                    );
                } else {
                    $this->flash->addMessage(
                        'error_detected',
                        _T("An error occurred while storing the contribution.", "objectslend")
                    );
                }
            }
        }

        $this->flash->addMessage(
            'success_detected',
            str_replace(
                '%object',
                $object->name,
                _T("You have just borrowed %object :)", "objectslend")
            )
        );

        if ($this->isAjax($request) || $post['mode'] == 'ajax') {
            return $this->withJson(
                $response,
                [
                    'success'   => 'true'
                ]
            );
        } else {
            // Redirection sur la liste des objets
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }
    }

    /**
     * Objects lends return action
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Objects ID
     */
    public function doReturn(Request $request, Response $response, int $id): Response
    {
        $lendsprefs = new Preferences($this->zdb);
        $post = $request->getParsedBody();

        $object_id = $id;

        $deps = [
            'rents'     => true,
            'last_rent' => true,
            'member'    => true
        ];

        //retrieve object information
        $object = new LendObject(
            $this->zdb,
            $object_id,
            $deps
        );

        if (
            !$this->canGiveBack($object, $lendsprefs)
        ) {
            Analog::log(
                'Trying to return an object without appropriate rights! (Object '
                . $id . ', user ' . $this->login->login . ')',
                Analog::WARNING
            );

            //redirect to objects list
            $this->flash->addMessage(
                'error_detected',
                _T("You do not have rights to return objects!", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }

        $last_rent = $object->getCurrentRent();
        if (
            $last_rent === null
            || $last_rent->in_stock
            || !$this->isAllowedStatus((int)($post['status'] ?? 0), LendStatus::getActiveStockStatuses($this->zdb))
        ) {
            Analog::log(
                'Trying to return an object that is not lent or with an invalid status! (Object '
                . $id . ', user ' . $this->login->login . ')',
                Analog::WARNING
            );

            $this->flash->addMessage(
                'error_detected',
                _T("This object cannot be returned.", "objectslend")
            );

            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }

        // close olds object rents
        LendRent::closeAllRentsForObject($object_id, '');

        // Ajout d'un nouveau statut "objet loué"
        $rent = new LendRent();
        $rent->object_id = $object_id;
        $rent->status_id = (int)$post['status'];
        $rent->store();

        $this->flash->addMessage(
            'success_detected',
            str_replace(
                '%object',
                $object->name,
                _T("%object has been returned :)", "objectslend")
            )
        );

        if ($this->isAjax($request) || $post['mode'] == 'ajax') {
            return $this->withJson(
                $response,
                [
                    'success'   => 'true'
                ]
            );
        } else {
            // Redirection sur la liste des objets
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('objectslend_objects')
                );
        }
    }

    /**
     * Can current user give back an object?
     *
     * Staff and admins always can; members only when they are allowed
     * to borrow objects and hold the object.
     *
     * @param LendObject  $object     Object
     * @param Preferences $lendsprefs Plugin preferences
     */
    private function canGiveBack(LendObject $object, Preferences $lendsprefs): bool
    {
        if ($this->login->isAdmin() || $this->login->isStaff()) {
            return true;
        }

        return $lendsprefs->{Preferences::PARAM_ENABLE_MEMBER_RENT_OBJECT}
            && $object->getIdAdh() !== null
            && $this->login->id == $object->getIdAdh();
    }

    /**
     * Is status part of allowed ones?
     *
     * @param int          $status_id Status ID
     * @param LendStatus[] $statuses  Allowed statuses
     */
    private function isAllowedStatus(int $status_id, array $statuses): bool
    {
        foreach ($statuses as $status) {
            if ($status->status_id === $status_id) {
                return true;
            }
        }
        return false;
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
        return $this->routeparser->urlFor('objectslend_objects');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'objectslend_doremove_object',
            $args
        );
    }

    /**
     * Get ID to remove
     *
     * In simple cases, we get the ID in the route arguments; but for
     * batchs, it should be found elsewhere.
     * In post values, we look for id key, as well as all {sthing}_sel keys (like members_sel or contrib_sel)
     *
     * @param array<string,mixed>  $args Request arguments
     * @param ?array<string,mixed> $post POST values
     *
     * @return null|int|int[]
     */
    protected function getIdsToRemove(array &$args, ?array $post): array|int|null
    {
        if (isset($args['id'])) {
            return (int)$args['id'];
        } else {
            $filters = $this->session->objectslend_filter_objects;
            return $filters->selected;
        }
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        if (isset($args['id'])) {
            //one object removal
            $object = new LendObject($this->zdb, (int)$args['id']);
            return sprintf(
                _T('Remove object %1$s', 'objectslend'),
                $object->name
            );
        } else {
            //batch objects removal
            $filters = $this->session->objectslend_filter_objects;
            return str_replace(
                '%count',
                count($filters->selected),
                _T('You are about to remove %count objects.', 'objectslend')
            );
        }
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        if (isset($this->session->objectslend_filter_objects)) {
            $filters =  $this->session->objectslend_filter_objects;
        } else {
            $filters = new ObjectsList();
        }
        $lendsprefs = new Preferences($this->zdb);
        $objects = new Objects($this->zdb, $lendsprefs, $filters);

        if (!is_array($post['id'])) {
            $ids = (array)$post['id'];
        } else {
            $ids = $post['id'];
        }

        $result = $objects->removeObjects($ids);
        if ($result) {
            unset($this->session->objectslend_filter_objects);
        }
        return $result;
    }

    // /CRUD - Delete
    // /CRUD
}
