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
use ImageGallery\Model\ImageGallery as ImageGalleryModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Thelia\Core\Event\CachedFileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileManager;

/**
 * Owns the on-disk lifecycle of the slide images: naming, writing, replacement of
 * the previous file and image cache purge.
 */
final readonly class SlideImageStorage
{
    public function __construct(
        private FileManager $fileManager,
        private Filesystem $filesystem,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Moves the uploaded file to the module upload directory and records its name
     * on the given variant of the slide.
     */
    public function store(ImageGalleryModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): void
    {
        $uploadDir = ImageGallery::getUploadDirPath();

        if (!$this->filesystem->exists($uploadDir)) {
            $this->filesystem->mkdir($uploadDir);
        }

        $previousFile = match ($variant) {
            ImageVariant::Desktop => $slide->getFile(),
            ImageVariant::Mobile => $slide->getMobileFile(),
        };

        $fileName = $this->buildFileName($slide, $uploadedFile, $variant);
        $filePath = $uploadDir.DS.$fileName;

        $this->filesystem->rename($uploadedFile->getPathname(), $filePath, true);
        $this->filesystem->chmod($filePath, 0o660);

        // Remove the previous file only when the new name differs: a re-upload of
        // the same source name produces the same deterministic name, and deleting
        // "the old one" would delete the file we just wrote.
        if ($previousFile !== null && $previousFile !== '' && $previousFile !== $fileName) {
            $this->filesystem->remove($uploadDir.DS.$previousFile);
        }

        match ($variant) {
            ImageVariant::Desktop => $slide->setFile($fileName),
            ImageVariant::Mobile => $slide->setMobileFile($fileName),
        };

        $slide->save();

        $this->clearImageCache();
    }

    /**
     * Removes slide images from disk and purges the image cache. Meant to be called
     * once the database deletion is committed, so a rolled back transaction never
     * loses the files. Paths that no longer exist are ignored.
     *
     * @param list<string> $paths absolute paths of the files to remove
     */
    public function removeFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $this->filesystem->remove($paths);

        $this->clearImageCache();
    }

    /**
     * Purges the gallery image cache: Thelia only regenerates a cached variant
     * when the file is missing, so a same-name replacement would keep serving the
     * stale image (and the previous file's variants would be orphaned) otherwise.
     */
    private function clearImageCache(): void
    {
        $this->eventDispatcher->dispatch(
            (new CachedFileEvent())->setCacheSubdirectory(ImageGallery::IMAGE_CACHE_SUBDIRECTORY),
            TheliaEvents::IMAGE_CLEAR_CACHE,
        );
    }

    private function buildFileName(ImageGalleryModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): string
    {
        // Derive the stored extension from the server-detected MIME type, never
        // from the client-controlled original name/extension: a ".php" uploaded
        // as an image would otherwise be symlinked, executable, into the web cache.
        $extension = MimeTypes::getDefault()->getExtensions($uploadedFile->getMimeType() ?? '')[0] ?? 'bin';
        $baseName = pathinfo($uploadedFile->getClientOriginalName(), \PATHINFO_FILENAME);

        // Suffix the variant so both images of a slide never collide on disk.
        return $this->fileManager->sanitizeFileName(
            \sprintf('%s-%d-%s.%s', $baseName, $slide->getId(), $variant->value, $extension),
        );
    }
}
