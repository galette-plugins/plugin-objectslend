<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteObjectsLend\Entity\CategoryPicture;
use GaletteObjectsLend\Entity\LendCategory;
use GaletteObjectsLend\Entity\LendObject;
use GaletteObjectsLend\Entity\ObjectPicture;
use GaletteObjectsLend\LendPreferences;
use GaletteObjectsLend\Repository\Objects;

use function Safe\copy;
use function Safe\filesize;
use function Safe\getimagesize;
use function Safe\unlink;

/**
 * Pictures tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture extends GaletteTestCase
{
    protected int $seed = 20260924183512;
    protected bool $load_plugins = true;

    /** @var string[] */
    private array $files = [];

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEND_PREFIX . LendObject::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEND_PREFIX . LendCategory::TABLE);
        $this->zdb->execute($delete);

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * Create an object
     */
    private function createObject(): LendObject
    {
        $object = new LendObject($this->zdb);
        $object->setName('An object');
        $object->store();
        return $object;
    }

    /**
     * Store a picture, a 800x400 JPEG
     *
     * @param ObjectPicture|CategoryPicture $picture Picture
     * @param int                           $id      Object or category identifier
     */
    private function storePicture(ObjectPicture|CategoryPicture $picture, int $id): void
    {
        $source = sys_get_temp_dir() . '/objectslend-upload-' . uniqid() . '.jpg';
        copy(GALETTE_ROOT . '../tests/fake_image.jpg', $source);
        $this->files[] = $source;

        $uploaded_file = new \Slim\Psr7\UploadedFile(
            fileNameOrStream: $source,
            name: 'fake_image.jpg',
            type: 'image/jpeg',
            size: filesize($source),
            error: UPLOAD_ERR_OK
        );
        $this->assertTrue($picture->storeFile($uploaded_file));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to remove picture database entry for ' . $id);
    }

    /**
     * Test thumbnail creation, and removal of files with the object
     */
    public function testThumbnail(): void
    {
        $prefs = new LendPreferences($this->preferences);
        $object = $this->createObject();
        $id = $object->getId();
        $this->storePicture(new ObjectPicture($id), $id);

        $store_path = GALETTE_PHOTOS_PATH . 'objectslend/objects/';
        $file = $store_path . $id . '.jpg';
        $thumb = $store_path . $id . '_th.jpg';
        $this->files[] = $file;
        $this->files[] = $thumb;

        $picture = new ObjectPicture($id);
        $this->assertTrue($picture->hasPicture());
        $this->assertFalse(is_file($thumb));
        $this->assertSame(128, $picture->getOptimalThumbWidth($prefs));
        $this->assertSame(64, $picture->getOptimalThumbHeight($prefs));
        $this->assertSame(realpath($thumb), realpath($picture->getThumb($prefs)));
        [$width, $height] = getimagesize($thumb);
        $this->assertSame([128, 64], [$width, $height]);

        //thumbnail is generated again when preferences change
        $this->assertTrue($this->preferences->setValue(LendPreferences::THUMB_MAX_WIDTH, 64, $this->login));
        $picture = new ObjectPicture($id);
        $this->assertSame(64, $picture->getOptimalThumbWidth($prefs));
        $this->assertSame(32, $picture->getOptimalThumbHeight($prefs));
        $this->expectLogEntry(\Analog\Analog::INFO, 'Picture thumbnail must be generated again.');
        [$width, $height] = getimagesize($thumb);
        $this->assertSame([64, 32], [$width, $height]);

        $response = $picture->displayThumb(new \Slim\Psr7\Response(), $prefs);
        $this->assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
        $this->assertSame(\Safe\file_get_contents($thumb), (string)$response->getBody());

        //files are removed with the object
        $object->delete();
        $this->assertFalse(is_file($file));
        $this->assertFalse(is_file($thumb));
        $this->assertFalse((new ObjectPicture($id))->hasPicture());
    }

    /**
     * Test thumbnail of the default picture
     */
    public function testDefaultThumbnail(): void
    {
        $prefs = new LendPreferences($this->preferences);
        $object = $this->createObject();

        $picture = $object->getPicture();
        $this->assertFalse($picture->hasPicture());

        //default picture is 512x512
        $this->assertSame(128, $picture->getOptimalThumbWidth($prefs));
        $this->assertSame(128, $picture->getOptimalThumbHeight($prefs));
        $this->assertSame(
            realpath(GALETTE_PHOTOS_PATH . 'objectslend/objects/1f5bc_th.png'),
            realpath($picture->getThumb($prefs))
        );

        //default thumbnail is shared, it is kept
        $object->delete();
        $this->assertTrue(is_file(GALETTE_PHOTOS_PATH . 'objectslend/objects/1f5bc_th.png'));
    }

    /**
     * Test files are removed with objects removed from the list
     */
    public function testRemoveObjects(): void
    {
        $prefs = new LendPreferences($this->preferences);
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $id = $this->createObject()->getId();
            $this->storePicture(new ObjectPicture($id), $id);
            (new ObjectPicture($id))->getThumb($prefs);
            $ids[] = $id;
        }
        $id = $this->createObject()->getId();
        $ids[] = $id;

        $store_path = GALETTE_PHOTOS_PATH . 'objectslend/objects/';
        foreach ($ids as $id) {
            $this->files[] = $store_path . $id . '.jpg';
            $this->files[] = $store_path . $id . '_th.jpg';
        }
        $this->assertTrue(is_file($store_path . $ids[0] . '_th.jpg'));

        $objects = new Objects($this->zdb, $this->preferences, $this->login, $prefs);
        $objects->removeObjects($ids);

        foreach ($ids as $id) {
            $this->assertFalse(is_file($store_path . $id . '.jpg'));
            $this->assertFalse(is_file($store_path . $id . '_th.jpg'));
        }
    }

    /**
     * Test files are removed with the category
     */
    public function testCategoryDelete(): void
    {
        $prefs = new LendPreferences($this->preferences);
        $category = new LendCategory($this->zdb);
        $category->setName('A category');
        $category->store();
        $id = $category->getId();
        $this->storePicture(new CategoryPicture($id), $id);

        $store_path = GALETTE_PHOTOS_PATH . 'objectslend/categories/';
        $file = $store_path . $id . '.jpg';
        $thumb = $store_path . $id . '_th.jpg';
        $this->files[] = $file;
        $this->files[] = $thumb;

        (new CategoryPicture($id))->getThumb($prefs);
        $this->assertTrue(is_file($thumb));

        $category->delete();
        $this->assertFalse(is_file($file));
        $this->assertFalse(is_file($thumb));
        $this->assertFalse((new CategoryPicture($id))->hasPicture());
    }
}
