<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Objects Lend',               //Name
    desc: 'Manage rent/lend of object',         //Short description
    author: 'Mélissa Djebel, Johan Cwiklinski', //Author
    version: '2.2.1',                           //Version
    compver: '1.3.0',                           //Galette version compatibility
    route: 'objectslend',                       //routing name and translation domain
    date: '2025-12-08',                         //Date
    acls: [
        'objectslend_preferences'       => 'admin',
        'store_objectlend_preferences'  => 'admin',
        'objectslend_category_add'      => 'staff',
        'objectslend_category_edit'     => 'staff',
        'objectslend_category_action_add' => 'staff',
        'objectslend_category_action_edit' => 'staff',
        'objectslend_categories'        => 'staff',
        'objectslend_filter_categories' => 'staff',
        'objectslend_remove_category'   => 'admin',
        'objectslend_doremove_category' => 'admin',
        'objectslend_status_add'        => 'staff',
        'objectslend_status_edit'       => 'staff',
        'objectslend_status_action_add' => 'staff',
        'objectslend_status_action_edit' => 'staff',
        'objectslend_statuses'          => 'staff',
        'objectslend_filter_statuses'   => 'staff',
        'objectslend_remove_status'     => 'admin',
        'objectslend_doremove_status'   => 'admin',
        'objectslend_object_add'        => 'staff',
        'objectslend_object_edit'       => 'staff',
        'objectslend_object_updatestatus' => 'staff',
        'objectslend_object_action_add' => 'staff',
        'objectslend_object_action_edit' => 'staff',
        'objectslend_object_clone'      => 'staff',
        'objectslend_object_doclone'    => 'staff',
        'objectslend_objects'           => 'member',
        'objectslend_filter_objects'    => 'member',
        'objectslend_remove_object'     => 'admin',
        'objectslend_doremove_object'   => 'admin',
        'objectslend_batch-objectslist' => 'staff',
        'objectslend_remove_objects'    => 'admin',
        'objectslend_objects_print'     => 'staff',
        'objectslend_object_print'      => 'staff',
        'objectslend_show_object_lend'  => 'staff',
        'objectslend_object_take'       => 'member',
        'objectslend_object_dotake'     => 'member',
        'objectslend_object_doreturn'   => 'member'
    ],
    dbver: 1.1
);
