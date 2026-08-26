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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets another module add keys to the flat read-model of a slide built by
 * `ImageGalleryPresenter::present()` — the array consumed by the back-office rows,
 * the front Twig component and the theme hook.
 *
 * Add your own keys (namespaced, e.g. `myModuleAudience`); overwriting a native key
 * is possible but unsupported, the module reserves them.
 */
class ImageGalleryRowEvent extends Event
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(
        protected ImageGallery $slide,
        protected array $row,
        protected string $locale,
    ) {
    }

    public function getSlide(): ImageGallery
    {
        return $this->slide;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function setRow(array $row): self
    {
        $this->row = $row;

        return $this;
    }

    /** Convenience for the common case: add (or replace) a single key. */
    public function set(string $key, mixed $value): self
    {
        $this->row[$key] = $value;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
