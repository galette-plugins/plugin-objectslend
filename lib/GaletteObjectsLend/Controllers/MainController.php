<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers;

use DI\Attribute\Inject;
use Galette\Controllers\AbstractPluginController;
use Galette\Entity\ContributionsTypes;
use GaletteObjectsLend\LendPreferences;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette objects lend main controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class MainController extends AbstractPluginController
{
    /**
     * @var array<string,mixed>
     */
    #[Inject("Plugin Galette Objects Lend")]
    protected array $module_info;

    /**
     * Objects lends preferences
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function preferences(Request $request, Response $response): Response
    {
        $ctypes = new ContributionsTypes($this->zdb);

        $params = [
            'page_title'    => _T('ObjectsLend preferences', 'objectslend'),
            'type_cotis_options'        => $ctypes->getList(),
            'lendsprefs'    => (new LendPreferences($this->preferences))->toArray()
        ];

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('preferences'),
            $params
        );
        return $response;
    }

    /**
     * Store objects lends preferences
     *
     * Only declared preferences are read from the request. A yes/no one
     * missing from it is an unchecked box, and is set off.
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function storePreferences(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $booleans = LendPreferences::getBooleans();

        $stored = true;
        $errors = [];
        foreach (array_keys(LendPreferences::getSchema()) as $name) {
            if (isset($booleans[$name])) {
                $value = (int)isset($post[$name]);
            } elseif (isset($post[$name])) {
                $value = trim((string)$post[$name]);
            } else {
                continue;
            }

            if (!$this->preferences->setValue($name, $value, $this->login)) {
                $stored = false;
                $errors = array_merge($errors, $this->preferences->getErrors());
            }
        }

        if ($stored) {
            $this->flash->addMessage(
                'success_detected',
                _T("Preferences have been successfully stored!", "objectslend")
            );
        } else {
            foreach (array_unique($errors) as $error) {
                $this->flash->addMessage('error_detected', $error);
            }
        }

        return $response
            ->withStatus(302)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('objectslend_preferences')
            );
    }
}
