<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Entity;

use Analog\Analog;
use GaletteObjectsLend\LendPreferences;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

use function Safe\file_get_contents;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\getimagesize;
use function Safe\rewind;
use function Safe\unlink;

/**
 * Picture handling
 *
 * @author Mélissa Djebel <melissa.djebel@gmx.net>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Picture extends \Galette\Core\Picture
{
    protected string $tbl_prefix = LEND_PREFIX;
    protected int $max_width = 800;
    protected int $max_height = 800;

    private string $thumb_path;
    private int $thumb_optimal_height;
    private int $thumb_optimal_width;

    /**
     * Set maximum size of an uploaded picture
     *
     * @param int $maxlength Maximum size, in Ko
     */
    public function setMaxLength(int $maxlength): self
    {
        $this->maxlength = $maxlength;
        return $this;
    }

    /**
     * Gets the default picture to show, anyway
     */
    protected function getDefaultPicture(): void
    {
        $this->format = 'png';
        $this->mime = 'image/png';
        $this->has_picture = false;
        $this->setDefaultPath(__DIR__ . '/../../../webroot/images/1f5bc.png');
    }

    /**
     * Display a thumbnail image, create it if necessary
     *
     * @param Response        $response Response
     * @param LendPreferences $prefs    Plugin preferences
     */
    public function displayThumb(Response $response, LendPreferences $prefs): Response
    {
        $this->setThumbSizes($prefs);
        $response = $response->withHeader('Content-Type', $this->mime)
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public');

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, file_get_contents($this->thumb_path));
        rewind($stream);

        return $response->withBody(new Stream($stream));
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
        //default picture thumbnail is shared
        if ($this->has_picture) {
            $thumb = $this->getThumbPath();
            if (is_file($thumb)) {
                unlink($thumb);
            }
        }

        return parent::delete($transaction);
    }

    /**
     * Get thumbnail file path
     */
    private function getThumbPath(): string
    {
        $infos = pathinfo($this->getPath());
        return $this->store_path . $infos['filename'] . '_th.' . ($infos['extension'] ?? '');
    }

    /**
     * Set picture thumbnail sizes, (re)create thumbnail if needed
     *
     * @param LendPreferences $prefs Plugin preferences
     */
    private function setThumbSizes(LendPreferences $prefs): void
    {
        $source = $this->getPath();
        $thumb = $this->getThumbPath();
        $max_width = $prefs->getThumbWidth();
        $max_height = $prefs->getThumbHeight();

        //same computation as resizeImage()
        $ratio = $this->getWidth() / $this->getHeight();
        if ($this->getWidth() > $this->getHeight()) {
            $expected = [$max_width, (int)round($max_width / $ratio)];
        } else {
            $expected = [(int)round($max_height * $ratio), $max_height];
        }

        if (is_file($thumb)) {
            [$width, $height] = getimagesize($thumb);
            if ([$width, $height] !== $expected) {
                Analog::log('Picture thumbnail must be generated again.', Analog::INFO);
                unlink($thumb);
            }
        }

        if (
            !is_file($thumb)
            && (
                !$this->ensureStorePath()
                || !$this->resizeImage(
                    source: $source,
                    ext: strtolower(pathinfo($source, PATHINFO_EXTENSION)),
                    dest: $thumb,
                    max_width: $max_width,
                    max_height: $max_height
                )
            )
        ) {
            Analog::log('Unable to create thumbnail for ' . $source . ', using picture itself.', Analog::WARNING);
            $thumb = $source;
        }

        [$width, $height] = getimagesize($thumb);
        $this->thumb_path = $thumb;
        $this->thumb_optimal_width = $width;
        $this->thumb_optimal_height = $height;
    }

    /**
     * Get thumbnail path, created if needed; picture itself if it cannot be created
     *
     * @param LendPreferences $prefs Plugin preferences
     */
    public function getThumb(LendPreferences $prefs): string
    {
        if (!isset($this->thumb_path)) {
            $this->setThumbSizes($prefs);
        }
        return $this->thumb_path;
    }

    /**
     * Returns current thumbnail optimal height
     *
     * @param LendPreferences $prefs Plugin preferences
     *
     * @return int optimal height
     */
    public function getOptimalThumbHeight(LendPreferences $prefs): int
    {
        if (!isset($this->thumb_optimal_height)) {
            $this->setThumbSizes($prefs);
        }
        return $this->thumb_optimal_height;
    }

    /**
     * Returns current thumbnail optimal width
     *
     * @param LendPreferences $prefs Plugin preferences
     *
     * @return int optimal width
     */
    public function getOptimalThumbWidth(LendPreferences $prefs): int
    {
        if (!isset($this->thumb_optimal_width)) {
            $this->setThumbSizes($prefs);
        }
        return $this->thumb_optimal_width;
    }
}
