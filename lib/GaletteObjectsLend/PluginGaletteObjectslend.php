<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Plugins\InstallableInterface;
use Galette\Core\Plugins\MenuProviderInterface;
use Galette\Core\Plugins\PreferencesProviderInterface;
use Galette\Core\GalettePlugin;
use GaletteObjectsLend\Entity\CategoryPicture;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendRent;
use GaletteObjectsLend\Entity\ObjectPicture;
use GaletteObjectsLend\Entity\LendStatus;

/**
 * Plugin Galette Objects Lend
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteObjectslend extends GalettePlugin implements InstallableInterface, MenuProviderInterface, PreferencesProviderInterface
{
    #[Inject]
    private readonly Db $zdb; //@phpstan-ignore property.uninitializedReadonly, property.onlyRead (injected from DI)
    #[Inject]
    private readonly Login $login; //@phpstan-ignore property.uninitializedReadonly, property.onlyRead (injected from DI)

    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string,mixed>>
     */
    public function getMenus(): array
    {
        $menus = [];

        $menus['galetteplugin_objectslends'] = [
            'title' => _T("Objects lend", "objectslend"),
            'icon' => 'briefcase',
            'items' => [
                [
                    'label' => _T("Objects list", "objectslend"),
                    'route' => [
                        'name' => 'objectslend_objects',
                        'aliases' => [
                            'objectslend_object_add',
                            'objectslend_object_edit',
                            'objectslend_show_object_lend',
                            'objectslend_object_take'
                        ]
                    ]
                ],
            ]
        ];

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $menus['galetteplugin_objectslends']['items'] = array_merge(
                $menus['galetteplugin_objectslends']['items'],
                [
                    [
                        'label' => _T("Borrow status", "objectslend"),
                        'route' => [
                            'name' => 'objectslend_statuses',
                            'aliases' => ['objectslend_status_add', 'objectslend_status_edit']
                        ]
                    ],
                    [
                        'label' => _T("Object categories", "objectslend"),
                        'route' => [
                            'name' => 'objectslend_categories',
                            'aliases' => ['objectslend_category_add', 'objectslend_category_edit']
                        ]
                    ],
                    [
                        'label' => _T("Preferences", "objectslend"),
                        'route' => [
                            'name' => 'objectslend_preferences'
                        ]
                    ]
                ]
            );
        }

        return $menus;
    }

    /**
     * Get the preferences the plugin declares
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPreferences(): array
    {
        return LendPreferences::getSchema();
    }

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string,mixed>>
     */
    public function getPublicMenus(): array
    {
        return [];
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return
            $this->zdb->tableExists(LEND_PREFIX . CategoryPicture::TABLE)
                && $this->zdb->tableExists(LEND_PREFIX . LendCategory::TABLE)
                && $this->zdb->tableExists(LEND_PREFIX . LendObject::TABLE)
                && $this->zdb->tableExists(LEND_PREFIX . LendRent::TABLE)
                && $this->zdb->tableExists(LEND_PREFIX . LendStatus::TABLE)
                && $this->zdb->tableExists(LEND_PREFIX . ObjectPicture::TABLE)
        ;
    }

    /**
     * Database version of tables installed before versions tracking
     *
     * Parameters table has been dropped in 1.1, when preferences moved to core.
     */
    public function getLegacyDbVersion(): ?float
    {
        return $this->zdb->tableExists(LEND_PREFIX . 'parameters') ? 1.0 : null;
    }
}
