<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ImageGallery\Loop;

use ImageGallery\Model\ImageGalleryQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Image;
use Thelia\Log\Tlog;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * Class ImageGalleryLoop.
 *
 * @author manuel raynaud <mraynaud@openstudio.fr>
 *
 * @deprecated will be removed in 2.0 — use the /api/front/image-galleries
 *             API resource or the `image_gallery` theme hook instead
 */
class ImageGallery extends Image
{
    protected function getArgDefinitions(): \Thelia\Core\Template\Loop\Argument\ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(['crop', 'borders', 'none'])
                ),
                'none'
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'])
                ),
                'manual'
            ),
            Argument::createAnyTypeArgument('effects'),
            Argument::createBooleanTypeArgument('allow_zoom', false),
            Argument::createBooleanTypeArgument('filter_disable_slides', true),
            Argument::createAlphaNumStringTypeArgument('group'),
            Argument::createAlphaNumStringTypeArgument('format')
        );
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \ImageGallery\Model\ImageGallery $gallery */
        foreach ($loopResult->getResultDataCollection() as $gallery) {
            $imgSourcePath = $gallery->getUploadDir().DS.$gallery->getFile();
            if (!file_exists($imgSourcePath)) {
                Tlog::getInstance()->error(sprintf('ImageGallery source image file %s does not exists.', $imgSourcePath));
                continue;
            }

            $startDate = $gallery->getStartDate();
            $endDate = $gallery->getEndDate();

            // Publication state is computed on the fly — never persisted from a read
            // path — through the single source of truth on the model. Only exposed as
            // the DISABLE row value: unpublished slides are already excluded by
            // filterByPublished() in buildModelCriteria() when filter_disable_slides
            // is on, so re-testing here would filter twice on the same rule.
            $isPublished = $gallery->isCurrentlyPublished();

            $loopResultRow = new LoopResultRow($gallery);

            $event = new ImageEvent();
            $event->setSourceFilepath($imgSourcePath)
                ->setCacheSubdirectory(\ImageGallery\ImageGallery::IMAGE_CACHE_SUBDIRECTORY);

            switch ($this->getResizeMode()) {
                case 'crop':
                    $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_CROP;
                    break;
                case 'borders':
                    $resize_mode = \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS;
                    break;
                case 'none':
                default:
                    $resize_mode = \Thelia\Action\Image::KEEP_IMAGE_RATIO;
            }

            // Prepare tranformations
            $width = $this->getWidth();
            $height = $this->getHeight();
            $rotation = $this->getRotation();
            $background_color = $this->getBackgroundColor();
            $quality = $this->getQuality();
            $effects = $this->getEffects();
            $format = $this->getFormat();

            if (null !== $width) {
                $event->setWidth($width);
            }
            if (null !== $height) {
                $event->setHeight($height);
            }
            $event->setResizeMode((string) $resize_mode);
            if (null !== $rotation) {
                $event->setRotation($rotation);
            }
            if (null !== $background_color) {
                $event->setBackgroundColor($background_color);
            }
            if (null !== $quality) {
                $event->setQuality($quality);
            }
            if (null !== $effects) {
                $event->setEffects(explode(',', (string) $effects));
            }
            if (null !== $format) {
                $event->setFormat($format);
            }

            $event->setAllowZoom($this->getAllowZoom());

            // Dispatch image processing event
            $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

            if ($startDate) {
                $startDate = $startDate->format('Y-m-d').'T'.$startDate->format('H:i');
            }
            if ($endDate) {
                $endDate = $endDate->format('Y-m-d').'T'.$endDate->format('H:i');
            }

            $loopResultRow
                ->set('ID', $gallery->getId())
                ->set('LOCALE', $this->locale)
                ->set('IMAGE_URL', $event->getFileUrl())
                ->set('ORIGINAL_IMAGE_URL', $event->getOriginalFileUrl())
                ->set('IMAGE_PATH', $event->getCacheFilepath())
                ->set('ORIGINAL_IMAGE_PATH', $event->getSourceFilepath())
                ->set('TITLE', $gallery->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $gallery->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $gallery->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $gallery->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('ALT', $gallery->getVirtualColumn('i18n_ALT'))
                ->set('URL', $gallery->getUrl())
                ->set('POSITION', $gallery->getPosition())
                ->set('DISABLE', $isPublished ? 0 : 1)
                ->set('GROUP', $gallery->getGroup())
                ->set('LIMITED', $gallery->getLimited())
                ->set('START_DATE', $startDate)
                ->set('END_DATE', $endDate)
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $search = ImageGalleryQuery::create();
        $group = $this->getGroup();

        $this->configureI18nProcessing($search, ['ALT', 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM']);

        $orders = $this->getOrder();

        // Results ordering
        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        if ($group) {
            $search->filterByGroup($group);
        }

        if ($this->getFilterDisableSlides()) {
            $search->filterByPublished();
        }

        return $search;
    }
}
