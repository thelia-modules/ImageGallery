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

use ImageGallery\Exception\MissingMobileImageException;
use ImageGallery\Model\ImageGallery as ImageGalleryModel;
use ImageGallery\Model\ImageGalleryQuery;
use ImageGallery\Model\Map\ImageGalleryTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImageGallerySlideService
{
    public function __construct(
        private SlideImageStorage $imageStorage,
        private ImageGalleryHtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function create(UploadedFile $file, UploadedFile $mobileFile, string $group, string $title, string $locale): ImageGalleryModel
    {
        return $this->inTransaction(function (ConnectionInterface $con) use ($file, $mobileFile, $group, $title, $locale): ImageGalleryModel {
            $slide = (new ImageGalleryModel())
                ->setGroup($group)
                ->setPosition($this->nextPositionInGroup($group))
                ->setDisable(0)
                ->setLimited(0);

            $slide
                ->setLocale($locale)
                ->setTitle($title)
                ->save($con);

            $this->attachImage($slide, $file, ImageVariant::Desktop);
            $this->attachImage($slide, $mobileFile, ImageVariant::Mobile);

            return $slide;
        });
    }

    /**
     * @param array<string, mixed> $data validated ImageGallerySlideForm data
     *
     * @throws MissingMobileImageException when the slide has no mobile image yet
     */
    public function update(ImageGalleryModel $slide, array $data, string $locale): void
    {
        // The mobile image is mandatory: a slide that has none (typically legacy
        // data) cannot be saved until one is uploaded. Only the image upload path
        // (attachImage) can lift the invariant.
        if (($slide->getMobileFile() ?? '') === '') {
            throw new MissingMobileImageException(\sprintf('Slide %d has no mobile image.', (int) $slide->getId()));
        }

        $this->inTransaction(function (ConnectionInterface $con) use ($slide, $data, $locale): void {
            $previousGroup = $slide->getGroup();
            $newGroup = (string) $data['group'];

            $slide
                ->setGroup($newGroup)
                ->setUrl($data['url'])
                ->setLinkTarget($data['link_target'])
                ->setDisable($data['visible'] ? 0 : 1)
                ->setLimited($data['limited'] ? 1 : 0)
                ->setStartDate($data['start_date'])
                ->setEndDate($data['end_date'])
                ->setLocale($locale)
                ->setTitle($data['title'])
                ->setAlt($data['alt'])
                ->setChapo($data['chapo'])
                ->setDescription($this->htmlSanitizer->sanitize($data['description']))
                ->setPostscriptum($data['postscriptum'])
                ->setButtonLabel($data['button_label']);

            if ($previousGroup !== $newGroup) {
                $slide->setPosition($this->nextPositionInGroup($newGroup));
            }

            $slide->save($con);

            if ($previousGroup !== $newGroup) {
                $this->renumberGroup($previousGroup, $con);
            }
        });
    }

    public function delete(int $slideId): void
    {
        $slide = ImageGalleryQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $group = $slide->getGroup();
        $imagePaths = $this->imagePaths($slide);

        $this->inTransaction(function (ConnectionInterface $con) use ($slide, $group): void {
            $slide->delete($con);
            $this->renumberGroup($group, $con);
        });

        // Images are removed only once the deletion is committed: a rolled back
        // transaction would otherwise leave a slide pointing at deleted files.
        $this->imageStorage->removeFiles($imagePaths);
    }

    public function updatePosition(int $slideId, int $position): void
    {
        $slide = ImageGalleryQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $siblings = ImageGalleryQuery::create()
            ->filterByGroup($slide->getGroup())
            ->filterById($slideId, Criteria::NOT_EQUAL)
            ->orderByPosition()
            ->find()
            ->getData();

        array_splice($siblings, max(0, $position - 1), 0, [$slide]);

        $this->inTransaction(function (ConnectionInterface $con) use ($siblings): void {
            $this->applySequentialPositions($siblings, $con);
        });
    }

    public function toggleVisibility(int $slideId): bool
    {
        $slide = ImageGalleryQuery::create()->findPk($slideId);

        if ($slide === null) {
            return false;
        }

        $visible = (bool) $slide->getDisable();
        $slide->setDisable($visible ? 0 : 1)->save();

        return $visible;
    }

    public function attachImage(ImageGalleryModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): void
    {
        $this->imageStorage->store($slide, $uploadedFile, $variant);
    }

    /**
     * Runs the callback in a transaction on the module connection, rolling back
     * and rethrowing on any failure.
     */
    private function inTransaction(\Closure $callback): mixed
    {
        $con = Propel::getConnection(ImageGalleryTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $result = $callback($con);

            $con->commit();

            return $result;
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<string> absolute paths of the images owned by the slide
     */
    private function imagePaths(ImageGalleryModel $slide): array
    {
        $uploadDir = $slide->getUploadDir();
        $paths = [];

        foreach ([$slide->getFile(), $slide->getMobileFile()] as $file) {
            if ($file !== null && $file !== '') {
                $paths[] = $uploadDir.DS.$file;
            }
        }

        return $paths;
    }

    private function nextPositionInGroup(?string $group): int
    {
        $maxPosition = ImageGalleryQuery::create()
            ->filterByGroup($group)
            ->orderByPosition(Criteria::DESC)
            ->findOne()
            ?->getPosition();

        return ($maxPosition ?? 0) + 1;
    }

    private function renumberGroup(?string $group, ConnectionInterface $con): void
    {
        $slides = ImageGalleryQuery::create()
            ->filterByGroup($group)
            ->orderByPosition()
            ->find();

        $this->applySequentialPositions($slides, $con);
    }

    /**
     * Numbers the slides 1..n in the given order, saving only the ones that move.
     *
     * @param iterable<ImageGalleryModel> $slides
     */
    private function applySequentialPositions(iterable $slides, ConnectionInterface $con): void
    {
        $position = 0;

        foreach ($slides as $slide) {
            ++$position;

            if ($slide->getPosition() !== $position) {
                $slide->setPosition($position)->save($con);
            }
        }
    }
}
