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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Purifies the slide rich-text (description) before it is stored, keeping only a
 * safe subset of formatting tags. The WYSIWYG editor is a UI hint only: the raw
 * POST body is attacker-controllable, so sanitisation must happen server-side.
 */
final readonly class ImageGalleryHtmlSanitizer
{
    private HtmlSanitizer $sanitizer;

    /**
     * The default policy can be substituted by recompiling the service with an
     * explicit configuration (a project tightening or widening the allow-list),
     * without touching the sanitisation call sites.
     */
    public function __construct(?HtmlSanitizerConfig $config = null)
    {
        $this->sanitizer = new HtmlSanitizer($config ?? self::defaultConfig());
    }

    /** Allow-list of the formatting a slide description may carry. */
    private static function defaultConfig(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig())
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('span')
            ->allowElement('a', ['href', 'title', 'target'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            // Everything not explicitly allowed above (script, style, event handlers,
            // inline styles…) is dropped by default — this is an allow-list.
            ->allowLinkSchemes(['https', 'http', 'mailto']);
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return $this->sanitizer->sanitize($html);
    }
}
