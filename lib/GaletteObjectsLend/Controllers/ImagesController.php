<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers;

use Galette\Controllers\ImagesController as GImagesController;
use GaletteObjectsLend\LendPreferences;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette objects lend images controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class ImagesController extends GImagesController
{
    private LendPreferences $lendsprefs;

    /**
     * Objects lends category or object route
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param string   $type     Requested type (category or object)
     * @param string   $mode     Either thumbnail or photo
     * @param ?int     $id       Object id
     */
    public function lendPicture(Request $request, Response $response, string $type, string $mode, ?int $id = null): Response
    {
        $class = '\GaletteObjectsLend\Entity\\'
            . ($type == 'category' ? 'CategoryPicture' : 'ObjectPicture');
        $picture = new $class($id);

        $this->lendsprefs = new LendPreferences($this->preferences);
        if ($mode == 'thumbnail') {
            return $picture->displayThumb($response, $this->lendsprefs);
        } else {
            return $picture->display($response);
        }
    }
}
