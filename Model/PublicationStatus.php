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

/**
 * Publication state of a slide, computed by ImageGallery::getPublicationStatus().
 * The backing values are the strings exposed to the templates and the API, so they
 * must stay stable.
 */
enum PublicationStatus: string
{
    case Online = 'online';
    case Disabled = 'disabled';
    case Scheduled = 'scheduled';
    case Expired = 'expired';

    /**
     * Bootstrap badge variant used by the back-office listing.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Online => 'success',
            self::Disabled => 'secondary',
            self::Scheduled => 'info',
            self::Expired => 'warning',
        };
    }
}
