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

namespace ImageGallery\Event;

use ImageGallery\Model\ImageGallery;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched around every slide create/update/delete (see ImageGalleryEvents).
 *
 * Listeners get the slide model and the COMPLETE validated form data — the fields
 * another module added through `FORM_AFTER_BUILD` are in the bag, so extenders can
 * persist them on the post-save events.
 *
 * Deliberately a plain Symfony `Event` rather than Thelia's `ActionEvent`: the
 * latter carries magic `__get`/`__set` property bags and form binding this module
 * never uses, and would silently answer `null` on a mistyped accessor.
 */
class ImageGallerySlideEvent extends Event
{
    public function __construct(
        protected ImageGallery $slide,
        protected ParameterBag $data = new ParameterBag(),
        protected ?string $locale = null,
    ) {
    }

    public function getSlide(): ImageGallery
    {
        return $this->slide;
    }

    /**
     * Validated form data, including the fields added by extender modules. Empty on
     * the delete events and on programmatic saves (the seed command, for instance):
     * always guard a read with `has()`.
     */
    public function getData(): ParameterBag
    {
        return $this->data;
    }

    /** Back-office edition locale used to persist the i18n fields, when known. */
    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
