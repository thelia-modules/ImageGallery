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

use ImageGallery\Model\ImageGalleryQuery;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets another module alter the query behind every list the module presents
 * (back-office tables, front Twig component, theme hook) before it runs: extra
 * filters, joins, virtual columns.
 *
 * `isBackendContext()` is true when the list feeds the back-office: an extender
 * applying a front-only filter (audience, customer group…) must skip it there, or
 * administrators would stop seeing their own slides.
 */
class ImageGalleryQueryEvent extends Event
{
    public function __construct(
        protected ImageGalleryQuery $query,
        protected bool $backendContext = false,
        protected ?string $group = null,
        protected ?string $locale = null,
    ) {
    }

    public function getQuery(): ImageGalleryQuery
    {
        return $this->query;
    }

    public function isBackendContext(): bool
    {
        return $this->backendContext;
    }

    /** The group being listed, null when every group is listed at once. */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
