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

/**
 * Business extension points of the ImageGallery module. Together with the stable
 * form names (extensible through `TheliaEvents::FORM_AFTER_BUILD . '.image_gallery_slide'`)
 * and the back-office rendering hooks declared by `ImageGallery::getHooks()`, these
 * events let another module add business fields to a slide, persist them and
 * filter/enrich the presented lists WITHOUT forking the module.
 *
 * See the Readme, « Extending the module (no fork needed) ».
 */
final class ImageGalleryEvents
{
    /**
     * Pre-save slide events, dispatched INSIDE the module transaction, right before
     * the model is written: carry the model and the full validated form data
     * (extra fields included). Alter the model here, never persist your own tables.
     */
    public const SLIDE_CREATE = 'image_gallery.slide.create';
    public const SLIDE_UPDATE = 'image_gallery.slide.update';
    public const SLIDE_DELETE = 'image_gallery.slide.delete';

    /**
     * Post-save slide events, dispatched AFTER the transaction is committed: the
     * slide has an id and its native fields are stored. This is where an extender
     * module persists (or cleans up) its own tables — a rolled back transaction
     * never reaches them.
     */
    public const SLIDE_CREATED = 'image_gallery.slide.created';
    public const SLIDE_UPDATED = 'image_gallery.slide.updated';
    public const SLIDE_DELETED = 'image_gallery.slide.deleted';

    /**
     * List extension: alter the `ImageGalleryQuery` before it runs (extra filters,
     * joins, virtual columns). Dispatched by `ImageGalleryPresenter` for both the
     * back-office lists and the front component/theme hook — check
     * `isBackendContext()` before applying a front-only filter.
     */
    public const QUERY_EXTEND = 'image_gallery.query.extend';

    /**
     * List extension: add keys to the flat read-model of a slide built by
     * `ImageGalleryPresenter::present()` (back-office rows, front component, theme hook).
     */
    public const ROW_ENRICH = 'image_gallery.row.enrich';

    private function __construct()
    {
    }
}
