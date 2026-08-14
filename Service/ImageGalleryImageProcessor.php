<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ImageGallery\Service;

use ImageGallery\ImageGallery;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Turns the stored file names of a slide into public URLs through the Thelia
 * image cache. Single owner of the image processing rules of the module: the
 * presenter (back-office, theme hook, Twig component) and the API normalizer
 * both go through it so a slide is rendered the same way everywhere.
 */
final readonly class ImageGalleryImageProcessor
{
    /** Ratio-preserving variant widths used to build the `srcset` attributes. */
    public const DESKTOP_SRCSET_WIDTHS = [768, 1280, 1920];
    public const MOBILE_SRCSET_WIDTHS = [480, 828];

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The image fields of a slide, built from its two file names.
     *
     * Single source of truth of the rule shared by the presenter and the API
     * normalizer: both URLs are always produced, while the `srcset` variants only
     * accompany full-size renders (front, preview) — a caller asking for an
     * explicit size (back-office thumbnails, `width`/`height` query parameters)
     * gets exactly that size.
     *
     * @return array{imageUrl: string|null, mobileImageUrl: string|null, imageSrcset: string|null, mobileImageSrcset: string|null}
     */
    public function imageFields(?string $file, ?string $mobileFile, ?int $width = null, ?int $height = null): array
    {
        $fullSize = $width === null && $height === null;

        return [
            'imageUrl' => $this->processedImageUrl($file, $width, $height),
            'mobileImageUrl' => $this->processedImageUrl($mobileFile, $width, $height),
            'imageSrcset' => $fullSize ? $this->processedSrcset($file, self::DESKTOP_SRCSET_WIDTHS) : null,
            'mobileImageSrcset' => $fullSize ? $this->processedSrcset($mobileFile, self::MOBILE_SRCSET_WIDTHS) : null,
        ];
    }

    public function processedImageUrl(?string $file, ?int $width = null, ?int $height = null): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $sourceFilepath = ImageGallery::getUploadDirPath().DS.$file;

        if (!is_file($sourceFilepath)) {
            return null;
        }

        $imageEvent = (new ImageEvent())
            ->setSourceFilepath($sourceFilepath)
            ->setCacheSubdirectory(ImageGallery::IMAGE_CACHE_SUBDIRECTORY);

        // The cache keys file names on getOptionsHash(), which is EMPTY unless
        // width, height, resize_mode AND background_color are ALL set — colliding
        // every render of a file on one cache entry. Hence the explicit background
        // and the square bound (height = width) on the ratio-preserving branch.
        if ($width !== null && $height !== null) {
            $imageEvent
                ->setWidth($width)
                ->setHeight($height)
                ->setBackgroundColor('ffffff')
                // The event stores the mode as string; the core casts it back to int.
                ->setResizeMode((string) \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS);
        } elseif ($width !== null || $height !== null) {
            // A single dimension (width-only or height-only) drives a ratio-preserving
            // resize. The square bound (missing side = provided side) keeps a distinct
            // cache hash per size while KEEP_IMAGE_RATIO stays driven by the real side.
            $size = $width ?? $height;
            $imageEvent
                ->setWidth($size)
                ->setHeight($size)
                ->setBackgroundColor('ffffff')
                ->setResizeMode((string) \Thelia\Action\Image::KEEP_IMAGE_RATIO);
        }

        $this->eventDispatcher->dispatch($imageEvent, TheliaEvents::IMAGE_PROCESS);

        return $imageEvent->getFileUrl();
    }

    /**
     * Builds a `srcset` attribute value from one source file: each width produces
     * a ratio-preserving variant through the Thelia image cache.
     *
     * @param int[] $widths
     */
    public function processedSrcset(?string $file, array $widths): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $entries = [];

        foreach ($widths as $width) {
            $url = $this->processedImageUrl($file, $width);

            if ($url !== null) {
                $entries[] = $url.' '.$width.'w';
            }
        }

        return $entries === [] ? null : implode(', ', $entries);
    }
}
