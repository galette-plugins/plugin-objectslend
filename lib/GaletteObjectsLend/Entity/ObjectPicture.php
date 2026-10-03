<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

/**
 * Picture for objects
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ObjectPicture extends Picture
{
    public const string PK = 'object_id';
    public const string TABLE = 'pictures';

    /**
     * Default constructor.
     *
     * @param mixed|null $objectid Object id
     */
    public function __construct(mixed $objectid = null)
    {
        $this->store_path = GALETTE_PHOTOS_PATH . 'objectslend/objects/';
        parent::__construct($objectid);
    }
}
