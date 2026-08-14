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

use ImageGallery\ImageGallery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Resolves how the back-office preview should render, transparently for the
 * administrator: the theme component is auto-detected (any registered Twig
 * component extending the module's base component — the documented override
 * contract) and the theme's "app" Encore entry is used when it exists. Both
 * can still be forced through module config values (developer override, no UI):
 * ImageGallery::CONFIG_PREVIEW_COMPONENT / ImageGallery::CONFIG_PREVIEW_ASSETS_ENTRIES.
 *
 * Also owns the filesystem prerequisite of that rendering (ensureFrontAssets).
 */
final readonly class PreviewSettings
{
    /**
     * @param list<string> $themeComponents collected by PreviewComponentPass
     */
    public function __construct(
        #[Autowire(param: 'image_gallery.preview.theme_components')]
        private array $themeComponents,
        private TemplateHelperInterface $templateHelper,
        private Filesystem $filesystem,
    ) {
    }

    /** The component to render in the preview, '' for the module default rendering. */
    public function component(): string
    {
        $override = trim((string) ImageGallery::getConfigValue(ImageGallery::CONFIG_PREVIEW_COMPONENT, ''));

        if ($override !== '') {
            return $override;
        }

        return $this->themeComponents[0] ?? '';
    }

    /**
     * @return list<string> Encore entries to load in the preview iframe
     */
    public function assetsEntries(): array
    {
        $override = trim((string) ImageGallery::getConfigValue(ImageGallery::CONFIG_PREVIEW_ASSETS_ENTRIES, ''));

        if ($override !== '') {
            return array_values(array_filter(array_map(trim(...), explode(',', $override))));
        }

        // Convention: the front theme's main Encore entry is named "app".
        try {
            $entrypoints = $this->templateHelper->getActiveFrontTemplate()->getAbsoluteAssetsPath().DS.'entrypoints.json';

            if (is_file($entrypoints)) {
                $manifest = json_decode((string) file_get_contents($entrypoints), true);

                if (isset($manifest['entrypoints']['app'])) {
                    return ['app'];
                }
            }
        } catch (\Throwable) {
            // No resolvable front template assets: preview without theme assets.
        }

        return [];
    }

    /**
     * Makes sure the public symlink to the front theme's built assets exists, so
     * the preview iframe's CSS/JS URLs resolve. Thelia normally creates it through
     * the TwigEngine EncoreExtension, but only during a front request: on a fresh
     * environment that only served admin pages so far, it is still missing.
     */
    public function ensureFrontAssets(): void
    {
        try {
            $frontTemplate = $this->templateHelper->getActiveFrontTemplate();

            if (!$frontTemplate->getAssetsPath()) {
                return;
            }

            $destination = THELIA_WEB_DIR.'templates-assets'.DS.$frontTemplate->getPath().DS.$frontTemplate->getAssetsPath();

            if (is_dir($destination) || !is_dir($frontTemplate->getAbsoluteAssetsPath())) {
                return;
            }

            $this->filesystem->symlink($frontTemplate->getAbsoluteAssetsPath(), $destination);
        } catch (\Throwable) {
            // Best effort: a missing symlink only degrades the preview styling.
        }
    }
}
