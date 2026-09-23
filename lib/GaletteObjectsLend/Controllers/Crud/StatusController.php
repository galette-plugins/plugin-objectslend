<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\Crud;

use Galette\Core\Pagination;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\Filters\StatusList;
use GaletteObjectsLend\Repository\Status;

/**
 * Status controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractListController<LendStatus, StatusList>
 */
class StatusController extends AbstractListController
{
    /**
     * Default filter name, used to store filters in session
     */
    public static function getDefaultFilterName(): string
    {
        return 'statuses';
    }

    /**
     * Routes name part
     */
    protected function getEntityRouteName(): string
    {
        return 'status';
    }

    /**
     * Name of the list route
     */
    protected function getListRouteName(): string
    {
        return 'objectslend_statuses';
    }

    /**
     * Create empty filters
     */
    protected function createFilters(): StatusList
    {
        return new StatusList();
    }

    /**
     * Apply posted filters specific to the list
     *
     * @param StatusList          $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyOwnFilters(Pagination $filters, array $post): void
    {
        if (isset($post['stock_filter']) && is_numeric($post['stock_filter'])) {
            $filters->stock_filter = $post['stock_filter'];
        }
    }

    /**
     * Get list template name and parameters
     *
     * @param StatusList $filters Filters
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    protected function getListView(Pagination $filters): array
    {
        $statuses = new Status($this->zdb, $this->preferences, $this->login, $filters);
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

        return [
            'status_list',
            [
                'page_title'    => _T("Status list", "objectslend"),
                'statuses'      => $list,
                'nb_status'     => count($list)
            ]
        ];
    }

    /**
     * Load an entity
     *
     * @param ?int $id Entity ID, null for a new one
     */
    protected function loadEntity(?int $id): LendStatus
    {
        return new LendStatus($this->zdb, $id);
    }

    /**
     * Fill status from posted values
     *
     * @param LendStatus          $entity Status
     * @param array<string,mixed> $post   Posted values
     */
    protected function fillEntity(LendCategory|LendStatus $entity, array $post): void
    {
        $days = trim($post['rent_day_number']);
        $entity
            ->setText($post['text'])
            ->setInStock(isset($post['in_stock']))
            ->setActive(isset($post['is_active']))
            ->setRentDayNumber(strlen($days) > 0 ? (int)$days : null);
    }

    /**
     * Get edit template name and parameters
     *
     * @param LendStatus $entity Status
     * @param string     $action Either add or edit
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    protected function getEditView(LendCategory|LendStatus $entity, string $action): array
    {
        if ($entity->getId() !== null) {
            $title = str_replace(
                '%status',
                $entity->getText(),
                _T("Edit status %status", "objectslend")
            );
        } else {
            $title = _T("New status", "objectslend");
        }

        return [
            'status_edit',
            [
                'page_title'    => $title,
                'status'        => $entity
            ]
        ];
    }

    /**
     * Message displayed once the entity has been stored
     */
    protected function getStoredMessage(): string
    {
        return _T("Status has been saved", "objectslend");
    }

    /**
     * Message displayed when the entity cannot be stored
     */
    protected function getStoreErrorMessage(): string
    {
        return _T("An error occurred while storing the status.", "objectslend");
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        return sprintf(
            _T('Remove status %1$s', 'objectslend'),
            $this->loadEntity((int)$args['id'])->getText()
        );
    }
}
