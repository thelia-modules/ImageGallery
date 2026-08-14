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

namespace ImageGallery\Hook\Theme;

use ImageGallery\ImageGallery;
use ImageGallery\Service\ImageGalleryPresenter;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Answers `theme_hook('image_gallery', { group: 'home' })` — its `imagegallery`
 * alias, or the shorthand `theme_hook('image_gallery.home')` — with the default
 * carousel markup.
 *
 * Note: `ThemeHookInterface` implementations are collected through the
 * `thelia.theme_hook` tag, but the current Thelia core ships no consumer for it
 * (no `theme_hook()` Twig function), so this hook is dormant until one exists.
 * Themes should use the `ImageGallery` Twig component or the front API resource
 * instead — see the Readme.
 */
final readonly class ImageGalleryThemeHook implements ThemeHookInterface
{
    public function __construct(
        private ImageGalleryPresenter $presenter,
        private RequestStack $requestStack,
        private Environment $twig,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return $hookName === 'image_gallery'
            || $hookName === 'imagegallery'
            || str_starts_with($hookName, 'image_gallery.');
    }

    public function render(string $hookName, array $parameters): string
    {
        $group = (string) ($parameters['group'] ?? (str_contains($hookName, '.') ? substr($hookName, \strlen('image_gallery.')) : 'home'));
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? ImageGallery::DEFAULT_LOCALE;

        $slides = $this->presenter->publishedSlides($group, $locale);

        if ($slides === []) {
            return '';
        }

        return $this->twig->render('@ImageGalleryModule/theme-hook/image-gallery.html.twig', [
            'group' => $group,
            'slides' => $slides,
            'autoplay' => (int) ($parameters['autoplay'] ?? 0),
        ]);
    }
}
