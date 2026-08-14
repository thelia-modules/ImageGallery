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

use ImageGallery\Model\ImageGallery as ImageGalleryModel;
use ImageGallery\Model\ImageGalleryQuery;

/**
 * Builds the flat read-model of a slide consumed by the back-office, the theme
 * hook and the Twig component. Image processing itself belongs to
 * ImageGalleryImageProcessor.
 */
final readonly class ImageGalleryPresenter
{
    public function __construct(
        private ImageGalleryImageProcessor $imageProcessor,
        private ImageGalleryHtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> slides indexed by group name, ordered by position
     */
    public function slidesByGroup(string $locale, ?int $thumbnailWidth = null, ?int $thumbnailHeight = null): array
    {
        $groups = [];

        $slides = ImageGalleryQuery::create()
            ->joinWithI18n($locale)
            ->orderByGroup()
            ->orderByPosition()
            ->find();

        foreach ($slides as $slide) {
            $groups[$slide->getGroup() ?? ''][] = $this->present($slide, $locale, $thumbnailWidth, $thumbnailHeight);
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>> published slides of the group, ordered by position
     */
    public function publishedSlides(string $group, string $locale, ?int $width = null, ?int $height = null): array
    {
        $slides = [];

        $query = ImageGalleryQuery::create()
            ->joinWithI18n($locale)
            ->filterByGroup($group)
            ->filterByPublished()
            ->orderByPosition();

        foreach ($query->find() as $slide) {
            $slides[] = $this->present($slide, $locale, $width, $height);
        }

        return $slides;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ImageGalleryModel $slide, string $locale, ?int $width = null, ?int $height = null): array
    {
        $slide->setLocale($locale);

        return [
            'id' => $slide->getId(),
            'group' => $slide->getGroup(),
            'position' => $slide->getPosition(),
            'visible' => !$slide->getDisable(),
            'limited' => (bool) $slide->getLimited(),
            'startDate' => $slide->getStartDate(),
            'endDate' => $slide->getEndDate(),
            // Exposed as the backing string: templates and API consumers compare it
            // to plain status names.
            'status' => $slide->getPublicationStatus()->value,
            'url' => $slide->getUrl(),
            'linkTarget' => $slide->getLinkTarget(),
            'title' => $slide->getTitle(),
            'alt' => $slide->getAlt(),
            'chapo' => $slide->getChapo(),
            // Sanitized again at read time so descriptions stored before the
            // write-time sanitizer (e.g. migrated 2.x data) are never rendered raw.
            'description' => $this->htmlSanitizer->sanitize($slide->getDescription()),
            'postscriptum' => $slide->getPostscriptum(),
            'buttonLabel' => $slide->getButtonLabel(),
            'file' => $slide->getFile(),
            'mobileFile' => $slide->getMobileFile(),
            ...$this->imageProcessor->imageFields($slide->getFile(), $slide->getMobileFile(), $width, $height),
        ];
    }

    /**
     * Default values of ImageGallerySlideForm for the given slide, keyed by form field
     * name. The i18n values (and the `locale` field) follow the locale currently set on
     * the model, so call setLocale() — or present() — beforehand when editing another
     * language.
     *
     * @return array<string, mixed>
     */
    public function slideFormDefaults(ImageGalleryModel $slide): array
    {
        return [
            'locale' => $slide->getLocale(),
            'title' => $slide->getTitle(),
            'alt' => $slide->getAlt(),
            'chapo' => $slide->getChapo(),
            // Same read-time sanitizing as present(): the form must never be fed the
            // raw stored markup of pre-sanitizer (migrated 2.x) descriptions.
            'description' => $this->htmlSanitizer->sanitize($slide->getDescription()),
            'postscriptum' => $slide->getPostscriptum(),
            'url' => $slide->getUrl(),
            'link_target' => $slide->getLinkTarget(),
            'button_label' => $slide->getButtonLabel(),
            'group' => $slide->getGroup(),
            'visible' => !$slide->getDisable(),
            'limited' => (bool) $slide->getLimited(),
            'start_date' => $slide->getStartDate(),
            'end_date' => $slide->getEndDate(),
        ];
    }
}
