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

use ImageGallery\Exception\MissingMobileImageException;
use ImageGallery\ImageGallery;
use ImageGallery\Form\ImageGalleryCreateForm;
use ImageGallery\Form\ImageGalleryImageForm;
use ImageGallery\Form\ImageGallerySlideForm;
use ImageGallery\Model\ImageGallery as ImageGalleryModel;
use ImageGallery\Model\ImageGalleryQuery;
use ImageGallery\Service\ImageGalleryPresenter;
use ImageGallery\Service\ImageGallerySlideService;
use ImageGallery\Service\ImageVariant;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/admin/module/image-gallery/create', name: 'image_gallery.create', methods: ['POST'])]
    public function createSlide(ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(ImageGalleryCreateForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slide = $slideService->create(
                $data['file'],
                $data['mobile_file'],
                $data['group'],
                $data['title'],
                $this->getCurrentEditionLocale(),
                // Full validated data, so the fields another module added to the
                // creation form through FORM_AFTER_BUILD reach the business events.
                $data,
            );

            return new RedirectResponse($this->editUrl($slide->getId()));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'image gallery slide creation',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );

            return $this->redirectToConfigurationPage();
        }
    }

    #[Route('/admin/module/image-gallery/edit/{slideId}', name: 'image_gallery.edit', methods: ['GET'], requirements: ['slideId' => '\d+'])]
    public function editSlide(int $slideId, ImageGalleryPresenter $presenter): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        $locale = $this->getCurrentEditionLocale();
        $presented = $presenter->present($slide, $locale, 550, 200);

        // The model → form field mapping belongs to the presenter (single owner of
        // the slide read-model); the controller only owns the edition locale.
        $form = $this->createForm(ImageGallerySlideForm::class, FormType::class, [
            ...$presenter->slideFormDefaults($slide),
            'locale' => $locale,
        ]);

        return $this->render('imagegallery/slide-edit', [
            'slide' => $presented,
            'slide_form' => $form->createView()->getView(),
            'image_form' => $this->createForm(ImageGalleryImageForm::class)->createView()->getView(),
            'locale' => $locale,
            'edit_language_id' => LangQuery::create()->filterByLocale($locale)->findOne()?->getId() ?? 1,
            'configuration_url' => $this->configurationUrl(),
        ]);
    }

    #[Route('/admin/module/image-gallery/edit/{slideId}', name: 'image_gallery.save', methods: ['POST'], requirements: ['slideId' => '\d+'])]
    public function saveSlide(int $slideId, ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(ImageGallerySlideForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slideService->update($slide, $data, $data['locale'] ?: $this->getCurrentEditionLocale());

            $this->addFlash(
                'success',
                Translator::getInstance()->trans('Slide saved.', [], ImageGallery::BO_DOMAIN),
            );
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'image gallery slide edition',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );
        } catch (MissingMobileImageException) {
            // The mobile image is mandatory (rule enforced by the slide service):
            // a slide without one — typically legacy data — cannot be saved until
            // a mobile image is uploaded.
            $this->addFlash(
                'danger',
                Translator::getInstance()->trans('Please upload a mobile image before saving this slide.', [], ImageGallery::BO_DOMAIN),
            );
        }

        return new RedirectResponse($this->editUrl($slideId));
    }

    #[Route('/admin/module/image-gallery/image/{slideId}/{variant}', name: 'image_gallery.image.upload', methods: ['POST'], requirements: ['slideId' => '\d+', 'variant' => 'desktop|mobile'])]
    public function uploadImage(int $slideId, string $variant, ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(ImageGalleryImageForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slideService->attachImage($slide, $data['file'], ImageVariant::from($variant));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'gallery image upload',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );
        }

        return new RedirectResponse($this->editUrl($slideId));
    }

    #[Route('/admin/module/image-gallery/update-position', name: 'image_gallery.update_position', methods: ['POST'])]
    public function updatePosition(Request $request, TokenProvider $tokenProvider, ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->updatePosition((int) $request->get('slide_id'), (int) $request->get('position'));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/module/image-gallery/toggle-visible/{slideId}', name: 'image_gallery.toggle', methods: ['GET'], requirements: ['slideId' => '\d+'])]
    public function toggleVisibility(int $slideId, Request $request, TokenProvider $tokenProvider, ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->toggleVisibility($slideId);

        return $this->redirectToConfigurationPage();
    }

    #[Route('/admin/module/image-gallery/delete', name: 'image_gallery.delete', methods: ['POST'])]
    public function deleteSlide(Request $request, TokenProvider $tokenProvider, ImageGallerySlideService $slideService): Response
    {
        if (null !== $response = $this->checkModuleAuth(AccessManager::DELETE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->delete((int) $request->get('slide_id'));

        return $this->redirectToConfigurationPage();
    }

    /**
     * The single expression of "who may act on this module": every action of the
     * controller guards itself with it, so the resource and the module code are
     * stated once instead of being repeated (and possibly diverging) per action.
     *
     * @return Response|null the response to return immediately, null when granted
     */
    private function checkModuleAuth(string $access): ?Response
    {
        return $this->checkAuth(AdminResources::MODULE, [ImageGallery::DOMAIN_NAME], $access);
    }

    private function findSlide(int $slideId): ?ImageGalleryModel
    {
        return ImageGalleryQuery::create()->findPk($slideId);
    }

    private function editUrl(int $slideId): string
    {
        return URL::getInstance()->absoluteUrl(
            $this->urlGenerator->generate('image_gallery.edit', ['slideId' => $slideId]),
        );
    }

    private function configurationUrl(): string
    {
        return URL::getInstance()->absoluteUrl(
            $this->urlGenerator->generate(ImageGallery::CONFIG_ROUTE, ['module_code' => ImageGallery::getModuleCode()]),
        );
    }

    private function redirectToConfigurationPage(): RedirectResponse
    {
        return new RedirectResponse($this->configurationUrl());
    }
}
