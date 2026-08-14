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

namespace ImageGallery\Exception;

/**
 * Raised when a slide without mobile image is saved: the mobile image is
 * mandatory, and legacy slides (created before it became mandatory) must get one
 * uploaded before any other change is accepted.
 */
class MissingMobileImageException extends \RuntimeException
{
}
