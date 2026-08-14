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

namespace ImageGallery\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Resource\I18n;

class ImageGalleryI18n extends I18n
{
    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $alt = null;

    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $title = null;

    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $chapo = null;

    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $description = null;

    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $postscriptum = null;

    #[Groups([ImageGallery::GROUP_ADMIN_READ, ImageGallery::GROUP_FRONT_READ])]
    public ?string $buttonLabel = null;

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getButtonLabel(): ?string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(?string $buttonLabel): self
    {
        $this->buttonLabel = $buttonLabel;

        return $this;
    }
}
