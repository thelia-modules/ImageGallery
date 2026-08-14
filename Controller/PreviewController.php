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

namespace ImageGallery\Controller;

use ImageGallery\ImageGallery;
use ImageGallery\Service\ImageGalleryPresenter;
use ImageGallery\Service\PreviewSettings;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Log\Tlog;

/**
 * Renders an image gallery group with the real published slides, as a standalone page
 * meant to be embedded in the back-office preview iframe. When the active theme
 * ships its own gallery component (auto-detected, see PreviewSettings), the
 * preview transparently shows the actual front rendering; otherwise it falls
 * back to the module's default front template.
 */
class PreviewController extends BaseAdminController
{
    #[Route('/admin/module/image-gallery/preview/{group}', name: 'image_gallery.preview', methods: ['GET'], requirements: ['group' => ImageGallery::GROUP_PATTERN])]
    public function preview(string $group, ImageGalleryPresenter $presenter, PreviewSettings $previewSettings): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, [ImageGallery::DOMAIN_NAME], AccessManager::VIEW)) {
            return $response;
        }

        $slides = $presenter->publishedSlides($group, $this->getCurrentEditionLocale());

        $component = $previewSettings->component();
        $assetsEntries = $previewSettings->assetsEntries();

        if ($component !== '') {
            $previewSettings->ensureFrontAssets();

            try {
                return $this->render('imagegallery/preview', [
                    'group' => $group,
                    'slides' => $slides,
                    'preview_component' => $component,
                    'preview_assets_entries' => $assetsEntries,
                ]);
            } catch (\Throwable $exception) {
                // The resolved component (or an Encore entry) cannot be rendered:
                // fall back to the module default rendering instead of a broken iframe.
                Tlog::getInstance()->addWarning(sprintf(
                    'ImageGallery preview: rendering with the theme component "%s" failed (%s), falling back to the default rendering.',
                    $component,
                    $exception->getMessage(),
                ));
            }
        }

        return $this->render('imagegallery/preview', [
            'group' => $group,
            'slides' => $slides,
            'preview_component' => '',
            'preview_assets_entries' => [],
        ]);
    }
}
