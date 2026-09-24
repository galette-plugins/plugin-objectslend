<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\AbstractPluginController;
use Galette\Entity\ContributionsTypes;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\SampleData;
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
            //a rent is not a membership fee
            'type_cotis_options'        => $ctypes->getList(false),
            'lendsprefs'    => (new LendPreferences($this->preferences))->toArray(),
            'sample_data'   => !(new SampleData($this->zdb))->hasObjects()
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

        //a membership fee would extend the membership, and needs an end date
        $type_id = $post[LendPreferences::GENERATED_CONTRIBUTION_TYPE_ID] ?? null;
        if (
            isset($post[LendPreferences::AUTO_GENERATE_CONTRIBUTION])
            && !isset((new ContributionsTypes($this->zdb))->getList(false)[(int)$type_id])
        ) {
            $stored = false;
            $errors[] = _T("Generated contributions must be of a donation type.", "objectslend");
            unset($post[LendPreferences::GENERATED_CONTRIBUTION_TYPE_ID]);
        }

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

    /**
     * Load sample data into an empty catalog
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function loadSampleData(Request $request, Response $response): Response
    {
        $sample = new SampleData($this->zdb);

        if ($sample->hasObjects()) {
            $this->flash->addMessage(
                'error_detected',
                _T("Sample data can only be loaded into an empty catalog.", "objectslend")
            );
        } else {
            try {
                $created = $sample->load($sample->getMembers());
                $this->flash->addMessage(
                    'success_detected',
                    sprintf(
                        //TRANS: %1$d is the number of objects, %2$d the number of rents
                        _T('Sample data loaded: %1$d objects, %2$d rents.', 'objectslend'),
                        $created['objects'],
                        $created['rents']
                    )
                );
            } catch (\Throwable $e) {
                Analog::log('Unable to load sample data | ' . $e->getMessage(), Analog::ERROR);
                $this->flash->addMessage(
                    'error_detected',
                    _T("Sample data could not be loaded.", "objectslend")
                );
            }
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->routeparser->urlFor('objectslend_objects'));
    }
}
