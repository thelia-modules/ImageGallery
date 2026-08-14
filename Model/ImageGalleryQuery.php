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

namespace ImageGallery\Model;

use ImageGallery\Model\Base\ImageGalleryQuery as BaseImageGalleryQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'imagegallery' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ImageGalleryQuery extends BaseImageGalleryQuery
{
    public function findAllByPosition()
    {
        return $this->orderByPosition()
            ->find();
    }

    /**
     * SQL counterpart of ImageGallery::isCurrentlyPublished(). The reference date is
     * bound from PHP instead of relying on the database NOW(): the MySQL and PHP time
     * zones may differ, which would move the publication window boundaries between the
     * SQL filter and the PHP rule.
     */
    public function filterByPublished(?\DateTimeInterface $now = null): static
    {
        $now ??= new \DateTime();
        $reference = $now->format('Y-m-d H:i:s');

        return $this
            ->where('(image_gallery.disable IS NULL OR image_gallery.disable = 0)')
            ->condition('image_gallery_unlimited', 'image_gallery.limited IS NULL OR image_gallery.limited = 0')
            ->condition('image_gallery_started', 'image_gallery.start_date <= ?', $reference)
            ->condition('image_gallery_not_ended', 'image_gallery.end_date >= ?', $reference)
            ->combine(['image_gallery_started', 'image_gallery_not_ended'], Criteria::LOGICAL_AND, 'image_gallery_window')
            ->combine(['image_gallery_unlimited', 'image_gallery_window'], Criteria::LOGICAL_OR);
    }
} // ImageGalleryQuery
