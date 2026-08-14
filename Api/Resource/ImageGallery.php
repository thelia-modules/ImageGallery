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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ImageGallery\Api\Bridge\Filter\ImageGalleryPublishedFilter;
use ImageGallery\Model\Map\ImageGalleryTableMap;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\Filter\SearchFilter;
use Thelia\Api\Resource\AbstractTranslatableResource;
use Thelia\Api\Resource\I18nCollection;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/image-galleries',
            name: self::ROUTE_ADMIN_GET_COLLECTION,
        ),
        new Get(
            uriTemplate: '/admin/image-galleries/{id}',
            name: self::ROUTE_ADMIN_GET,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/front/image-galleries',
            name: self::ROUTE_FRONT_GET_COLLECTION,
            // Without it, the items of hydra:member would carry the IRI of the
            // first declared operation of the class — an /api/admin/… @id served
            // by the public front endpoint.
            itemUriTemplate: '/front/image-galleries/{id}',
        ),
        new Get(
            uriTemplate: '/front/image-galleries/{id}',
            name: self::ROUTE_FRONT_GET,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
#[ApiFilter(
    filterClass: SearchFilter::class,
    properties: [
        'id',
        'group' => 'exact',
    ],
)]
#[ApiFilter(
    filterClass: OrderFilter::class,
    properties: [
        'position',
    ],
)]
#[ApiFilter(
    filterClass: ImageGalleryPublishedFilter::class,
    properties: [
        'published',
    ],
)]
class ImageGallery extends AbstractTranslatableResource
{
    public const ROUTE_ADMIN_GET_COLLECTION = 'api_image_gallery_admin_get_collection';
    public const ROUTE_ADMIN_GET = 'api_image_gallery_admin_get';
    public const ROUTE_FRONT_GET_COLLECTION = 'api_image_gallery_front_get_collection';
    public const ROUTE_FRONT_GET = 'api_image_gallery_front_get';

    public const GROUP_ADMIN_READ = 'admin:image_gallery:read';
    public const GROUP_FRONT_READ = 'front:image_gallery:read';

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $id = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $file = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?string $mobileFile = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $group = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?int $position = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $url = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $linkTarget = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $disable = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $limited = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $startDate = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTime $endDate = null;

    /** Processed image URLs and srcsets, filled by ImageGalleryNormalizer (not mapped to a column). */
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $imageUrl = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $mobileImageUrl = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $imageSrcset = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public ?string $mobileImageSrcset = null;

    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_FRONT_READ])]
    public I18nCollection $i18ns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getMobileFile(): ?string
    {
        return $this->mobileFile;
    }

    public function setMobileFile(?string $mobileFile): self
    {
        $this->mobileFile = $mobileFile;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function setGroup(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getLinkTarget(): ?string
    {
        return $this->linkTarget;
    }

    public function setLinkTarget(?string $linkTarget): self
    {
        $this->linkTarget = $linkTarget;

        return $this;
    }

    public function getDisable(): ?int
    {
        return $this->disable;
    }

    public function setDisable(?int $disable): self
    {
        $this->disable = $disable;

        return $this;
    }

    public function getLimited(): ?int
    {
        return $this->limited;
    }

    public function setLimited(?int $limited): self
    {
        $this->limited = $limited;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getMobileImageUrl(): ?string
    {
        return $this->mobileImageUrl;
    }

    public function setMobileImageUrl(?string $mobileImageUrl): self
    {
        $this->mobileImageUrl = $mobileImageUrl;

        return $this;
    }

    public function getImageSrcset(): ?string
    {
        return $this->imageSrcset;
    }

    public function setImageSrcset(?string $imageSrcset): self
    {
        $this->imageSrcset = $imageSrcset;

        return $this;
    }

    public function getMobileImageSrcset(): ?string
    {
        return $this->mobileImageSrcset;
    }

    public function setMobileImageSrcset(?string $mobileImageSrcset): self
    {
        $this->mobileImageSrcset = $mobileImageSrcset;

        return $this;
    }

    public function getI18ns(): I18nCollection
    {
        return $this->i18ns;
    }

    public function setI18ns(I18nCollection|array $i18ns): self
    {
        $this->i18ns = $i18ns;

        return $this;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new ImageGalleryTableMap();
    }

    public static function getI18nResourceClass(): string
    {
        return ImageGalleryI18n::class;
    }
}
