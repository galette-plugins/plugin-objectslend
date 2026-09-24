<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\Crud;

use Analog\Analog;
use Galette\Core\Pagination;
use GaletteObjectsLend\Entity\CategoryPicture;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendStatus;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\Filters\CategoriesList;
use GaletteObjectsLend\Repository\Categories;
use Slim\Psr7\Request;

/**
 * Categories controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractListController<LendCategory, CategoriesList>
 */
class CategoriesController extends AbstractListController
{
    /**
     * Default filter name, used to store filters in session
     */
    public static function getDefaultFilterName(): string
    {
        return 'categories';
    }

    /**
     * Routes name part
     */
    protected function getEntityRouteName(): string
    {
        return 'category';
    }

    /**
     * Name of the list route
     */
    protected function getListRouteName(): string
    {
        return 'objectslend_categories';
    }

    /**
     * Create empty filters
     */
    protected function createFilters(): CategoriesList
    {
        return new CategoriesList();
    }

    /**
     * Get list template name and parameters
     *
     * @param CategoriesList $filters Filters
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    protected function getListView(Pagination $filters): array
    {
        $categories = new Categories($this->zdb, $this->preferences, $this->login, $filters);
        $list = $categories->getCategoriesList(true);

        return [
            'categories_list',
            [
                'page_title'    => _T("Categories list", "objectslend"),
                'categories'    => $list,
                'nb_categories' => count($list),
                'olendsprefs'   => new LendPreferences($this->preferences),
                'time'          => time()
            ]
        ];
    }

    /**
     * Load an entity
     *
     * @param ?int $id Entity ID, null for a new one
     */
    protected function loadEntity(?int $id): LendCategory
    {
        return new LendCategory($this->zdb, $id);
    }

    /**
     * Fill category from posted values
     *
     * @param LendCategory        $entity Category
     * @param array<string,mixed> $post   Posted values
     */
    protected function fillEntity(LendCategory|LendStatus $entity, array $post): void
    {
        $entity
            ->setName($post['name'])
            ->setActive(($post['is_active'] ?? false) == true);
    }

    /**
     * Get edit template name and parameters
     *
     * @param LendCategory $entity Category
     * @param string       $action Either add or edit
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    protected function getEditView(LendCategory|LendStatus $entity, string $action): array
    {
        return [
            'category_edit',
            [
                'page_title'    => $entity->getId() !== null
                    ? _T("Edit category", "objectslend")
                    : _T("New category", "objectslend"),
                'category'      => $entity,
                'time'          => time(),
                'olendsprefs'   => new LendPreferences($this->preferences),
                'picture'       => new CategoryPicture($entity->getId())
            ]
        ];
    }

    /**
     * Upload or remove picture once the category has been stored
     *
     * @param LendCategory        $entity  Category
     * @param Request             $request PSR Request
     * @param array<string,mixed> $post    Posted values
     *
     * @return array<string> Errors
     */
    protected function afterStore(LendCategory|LendStatus $entity, Request $request, array $post): array
    {
        $errors = [];
        $picture = new CategoryPicture($entity->getId());
        if (!$picture->upload($request->getUploadedFiles(), 'picture')) {
            $errors = $picture->uploadErrors();
        }

        if (isset($post['del_photo']) && !$picture->delete()) {
            $errors[] = _T("Delete failed", "objectslend");
            Analog::log(
                'Unable to delete picture for category #' . $entity->getId(),
                Analog::ERROR
            );
        }
        return $errors;
    }

    /**
     * Message displayed once the entity has been stored
     */
    protected function getStoredMessage(): string
    {
        return _T("Category has been saved", "objectslend");
    }

    /**
     * Message displayed when the entity cannot be stored
     */
    protected function getStoreErrorMessage(): string
    {
        return _T("An error occurred while storing the category.", "objectslend");
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        return sprintf(
            _T('Remove category %1$s', 'objectslend'),
            $this->loadEntity((int)$args['id'])->getName(false)
        );
    }
}
