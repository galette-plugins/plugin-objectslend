<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

use Analog\Analog;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

/**
 * Picture handling
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture extends \Galette\Core\Picture
{
    protected int $max_width = 800;
    protected int $max_height = 800;

    protected int $thumb_max_width;
    protected int $thumb_max_height;

    protected int $thumb_optimal_height;
    protected int $thumb_optimal_width;

    /**
     * Default constructor.
     *
     * @param mixed|null $objectid Object id
     */
    public function __construct(mixed $objectid = null)
    {
        $this->tbl_prefix = LEND_PREFIX;

        if (!file_exists($this->store_path)) {
            if (!mkdir($this->store_path, 0o755, true)) {
                Analog::log(
                    'Unable to create photo dir `' . $this->store_path . '`.',
                    Analog::ERROR
                );
            } else {
                Analog::log(
                    'New directory `' . $this->store_path . '` has been created',
                    Analog::INFO
                );
            }
        } elseif (!is_dir($this->store_path)) {
            Analog::log(
                'Unable to store plugin images, since `' . $this->store_path
                . '` is not a directory.',
                Analog::WARNING
            );
        }

        parent::__construct($objectid);
    }

    /**
     * Gets the default picture to show, anyway
     *
     * @see Logo::getDefaultPicture()
     */
    protected function getDefaultPicture(): void
    {
        $this->file_path = (string)realpath(
            __DIR__ . '/../../../webroot/images/1f5bc.png'
        );
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->has_picture = false;
    }

    /**
     * Display a thumbnail image, create it if necessary
     *
     * @param Response    $response Response
     * @param Preferences $prefs    Preferences instance
     */
    public function displayThumb(Response $response, Preferences $prefs): Response
    {
        $this->setThumbSizes($prefs);
        $response = $response->withHeader('Content-Type', $this->mime)
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, file_get_contents($this->getThumbPath()));
        rewind($stream);

        return $response->withBody(new Stream($stream));
    }

    /**
     * Create thumbnail image
     * @see \Galette\Core\Picture::resizeImage()
     *
     * @param string  $source the source image
     * @param string  $ext    file's extension
     * @param ?string $dest   the destination image.
     *                        If null, we'll use the source image. Defaults to null
     */
    private function createThumb(string $source, string $ext, ?string $dest = null): bool
    {
        $class = get_class($this);

        if (function_exists("gd_info")) {
            $gdinfo = gd_info();
            $h = $this->thumb_max_height;
            $w = $this->thumb_max_width;
            if ($dest == null) {
                $dest = $source;
            }

            switch (strtolower($ext)) {
                case 'jpg':
                    if (!$gdinfo['JPEG Support']) {
                        Analog::log(
                            '[' . $class . '] GD has no JPEG Support - '
                            . 'pictures could not be resized!',
                            Analog::ERROR
                        );
                        return false;
                    }
                    break;
                case 'png':
                    if (!$gdinfo['PNG Support']) {
                        Analog::log(
                            '[' . $class . '] GD has no PNG Support - '
                            . 'pictures could not be resized!',
                            Analog::ERROR
                        );
                        return false;
                    }
                    break;
                case 'gif':
                    if (!$gdinfo['GIF Create Support']) {
                        Analog::log(
                            '[' . $class . '] GD has no GIF Support - '
                            . 'pictures could not be resized!',
                            Analog::ERROR
                        );
                        return false;
                    }
                    break;
                default:
                    return false;
            }

            [$cur_width, $cur_height] = getimagesize($source);

            $ratio = $cur_width / $cur_height;

            // calculate image size according to ratio
            if ($cur_width > $cur_height) {
                $h = (int)($w / $ratio);
            } else {
                $w = (int)($h * $ratio);
            }

            $thumb = imagecreatetruecolor($w, $h);
            switch ($ext) {
                case 'jpg':
                    $image = imagecreatefromjpeg($source);
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                    imagejpeg($thumb, $dest);
                    break;
                case 'png':
                    $image = imagecreatefrompng($source);
                    // Turn off alpha blending and set alpha flag. That prevent alpha
                    // transparency to be saved as an arbitrary color (black in my tests)
                    imagealphablending($thumb, false);
                    imagealphablending($image, false);
                    imagesavealpha($thumb, true);
                    imagesavealpha($image, true);
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                    imagepng($thumb, $dest);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($source);
                    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $cur_width, $cur_height);
                    imagegif($thumb, $dest);
                    break;
            }

            return true;
        } else {
            Analog::log(
                '[' . $class . '] GD is not present - '
                . 'pictures could not be resized!',
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Deletes a picture, from both database and filesystem
     *
     * @param bool $transaction Whether to use a transaction here or not
     *
     * @return bool true if image was successfully deleted, false otherwise
     */
    public function delete(bool $transaction = true): bool
    {
        //find and delete any thumb
        $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
        $filename = substr($this->file_path, 0, strlen($this->file_path) - strlen($ext) - 1);

        $thumb = $filename . '_th.' . $ext;

        if (file_exists($thumb)) {
            unlink($thumb);
        }

        return parent::delete($transaction);
    }

    /**
     * Stores an image on the disk and in the database
     *
     * @param UploadedFileInterface $file     The uploaded file
     * @param ?array<string, mixed> $cropping Cropping properties
     *
     * @return true|int
     */
    public function storeFile(UploadedFileInterface $file, ?array $cropping = null): bool|int
    {
        $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
        $filename = substr($this->file_path, 0, strlen($this->file_path) - strlen($ext) - 1);
        $thumb = $filename . '_th.' . $ext;

        if (is_file($thumb)) {
            unlink($thumb);
        }

        return parent::storeFile($file, $cropping);
    }

    /**
     * Get thumbnail file path
     */
    public function getThumbPath(): string
    {
        if ($this->has_picture) {
            $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
            $filename = substr($this->file_path, 0, strlen($this->file_path) - strlen($ext) - 1);
            $filename .= '_th.' . $ext;
        } else {
            $this->getDefaultPicture();
            $infos = pathinfo($this->file_path);
            $filename = $this->store_path . '/' . $infos['filename'] . '_th' . '.' . $infos['extension'];
        }
        return $filename;
    }

    /**
     * Set picture thumbnail sizes
     *
     * Should override Picture::setSize(), but this one is private :/
     *
     * @param Preferences $prefs Preferences instance
     */
    private function setThumbSizes(Preferences $prefs): void
    {
        $thumb = $this->getThumbPath();
        $this->thumb_max_width = $prefs->getThumbWidth();
        $this->thumb_max_height = $prefs->getThumbHeight();

        // Create if missing
        if (!is_file($thumb)) {
            $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
            $this->createThumb($this->file_path, $ext, $thumb);
        } else {
            //resize if too small/large
            if (function_exists("gd_info")) {
                [$cur_width, $cur_height] = getimagesize($thumb);

                if (
                    $cur_height != $this->getOptimalHeight()
                    && $cur_height < $this->thumb_max_height
                    && $cur_width != $this->getOptimalWidth()
                    && $cur_width < $this->thumb_max_width
                    || $cur_width > $this->thumb_max_width
                    || $cur_height > $this->thumb_max_height
                ) {
                    Analog::log(
                        'Picture thumbnail must be generated again.',
                        Analog::INFO
                    );
                    unlink($thumb);
                    $ext = pathinfo($this->file_path, PATHINFO_EXTENSION);
                    $this->createThumb($this->file_path, $ext, $thumb);
                }
            }
        }

        [$width, $height] = getimagesize($thumb);
        $this->thumb_optimal_height = (int)$height;
        $this->thumb_optimal_width = (int)$width;
    }

    /**
     * Returns current thumbnail optimal height
     *
     * @param Preferences $prefs Preferences instance
     *
     * @return int optimal height
     */
    public function getOptimalThumbHeight(Preferences $prefs): int
    {
        if (!isset($this->thumb_optimal_height)) {
            $this->setThumbSizes($prefs);
        }
        return (int)round($this->thumb_optimal_height, 1);
    }

    /**
     * Returns current thumbnail optimal width
     *
     * @param Preferences $prefs Preferences instance
     *
     * @return int optimal width
     */
    public function getOptimalThumbWidth(Preferences $prefs): int
    {
        if (!isset($this->thumb_optimal_width)) {
            $this->setThumbSizes($prefs);
        }
        return (int)round($this->thumb_optimal_width, 1);
    }

    /**
     * Get storage directory
     */
    public function getDir(): string
    {
        return $this->store_path;
    }
}
