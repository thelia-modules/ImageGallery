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

namespace ImageGallery\DependencyInjection;

use ImageGallery\Twig\Components\ImageGallery as ImageGalleryComponent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the Twig components that extend the module's base ImageGallery component
 * (the documented theme-override contract), so the back-office preview can
 * transparently render the theme's own gallery rendering instead of the neutral template.
 */
final class PreviewComponentPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $candidates = [];

        foreach ($container->findTaggedServiceIds('twig.component') as $id => $tags) {
            $class = $container->findDefinition($id)->getClass();

            if ($class === null || !is_subclass_of($class, ImageGalleryComponent::class)) {
                continue;
            }

            foreach ($tags as $tag) {
                if (isset($tag['key']) && $tag['key'] !== '') {
                    $candidates[] = (string) $tag['key'];
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        sort($candidates);

        $container->setParameter('image_gallery.preview.theme_components', $candidates);
    }
}
