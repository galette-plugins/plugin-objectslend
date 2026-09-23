<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers;

use Galette\Controllers\PdfController as GPdfController;
use GaletteObjectsLend\Entity\Preferences;
use GaletteObjectsLend\Filters\ObjectsList;
use GaletteObjectsLend\Repository\Objects;
use GaletteObjectsLend\IO\PdfObject;
use GaletteObjectsLend\IO\PdfObjects;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette objects lend PDF controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PdfController extends GPdfController
{
    /**
     * Object lends print object
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param int      $id       Object ID
     */
    public function printObject(Request $request, Response $response, int $id): Response
    {
        $lendsprefs = new Preferences($this->zdb);
        $object = (new Objects($this->zdb, $lendsprefs))->getWithCurrentRent($id);

        $pdf = new PdfObject(
            $this->zdb,
            $this->preferences,
            $lendsprefs
        );
        $pdf->drawCards([$object]);
        return $this->sendResponse($response, $pdf);
    }

    /**
     * Objects lends print objects
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     */
    public function printObjects(Request $request, Response $response): Response
    {
        $lendsprefs = new Preferences($this->zdb);

        if (isset($this->session->objectslend_filter_objects)) {
            $filters =  clone $this->session->objectslend_filter_objects;
        } else {
            $filters = new ObjectsList();
        }

        if ($filters->orderby !== Objects::ORDERBY_CATEGORY) {
            $filters->orderby = Objects::ORDERBY_CATEGORY;
        }
        $objects = new Objects($this->zdb, $lendsprefs, $filters);
        $list = $objects->getObjectsList(true, null, true, false);

        $pdf = new PdfObjects(
            $this->zdb,
            $this->preferences,
            $lendsprefs,
            $filters,
            $this->login
        );

        $pdf->drawList($list);
        return $this->sendResponse($response, $pdf);
    }
}
