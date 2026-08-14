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

namespace ImageGallery\Hook;

use ImageGallery\ImageGallery;
use ImageGallery\Model\PublicationStatus;
use ImageGallery\Service\ImageGalleryPresenter;
use ImageGallery\Service\PreviewSettings;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Tools\URL;

class BackHook extends BaseHook
{
    public function __construct(
        private readonly ImageGalleryPresenter $presenter,
        private readonly PreviewSettings $previewSettings,
        private readonly UrlGeneratorInterface $urlGenerator,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                'type' => 'back',
                'method' => 'onMainTopMenuTools',
            ],
            'module.configuration' => [
                'type' => 'back',
                'method' => 'renderModuleConfiguration',
            ],
            'module.config-js' => [
                'type' => 'back',
                'method' => 'renderModuleConfigJs',
            ],
        ];
    }

    /**
     * Add a new entry in the admin tools menu.
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add(
            [
                'id' => 'tools_menu_image_gallery',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl(
                    $this->urlGenerator->generate(ImageGallery::CONFIG_ROUTE, ['module_code' => ImageGallery::getModuleCode()]),
                ),
                'title' => $this->trans('Edit your image gallery', [], ImageGallery::DOMAIN_NAME),
            ]
        );
    }

    public function renderModuleConfiguration(HookRenderEvent $event): void
    {
        $locale = $this->getRequest()?->getLocale() ?? ImageGallery::DEFAULT_LOCALE;

        $event->add($this->render('imagegallery/hook/module-configuration.html.twig', [
            'groups' => $this->presenter->slidesByGroup($locale, 220, 80),
            'status_labels' => $this->statusLabels(),
            'preview_component' => $this->previewSettings->component(),
            // The preview is addressed by group name: migrated data may carry NULL or
            // otherwise invalid group names, which the template filters out with this
            // pattern instead of building a broken preview URL.
            'group_pattern' => ImageGallery::GROUP_PATTERN,
        ]));
    }

    public function renderModuleConfigJs(HookRenderEvent $event): void
    {
        $event->add($this->render('imagegallery/hook/module-config-js.html.twig'));
    }

    /**
     * Translated labels of the publication statuses, keyed by status value. The label
     * and the Bootstrap badge variant are both consumed by the configuration template,
     * which derives its BoDataTable badge variant map from this data.
     *
     * @return array<string, array{label: string, variant: string}>
     */
    private function statusLabels(): array
    {
        $labels = [];

        foreach (PublicationStatus::cases() as $status) {
            $translationKey = match ($status) {
                PublicationStatus::Online => 'Online',
                PublicationStatus::Disabled => 'Disabled',
                PublicationStatus::Scheduled => 'Scheduled',
                PublicationStatus::Expired => 'Expired',
            };

            $labels[$status->value] = [
                'label' => $this->trans($translationKey, [], ImageGallery::BO_DOMAIN),
                'variant' => $status->badgeVariant(),
            ];
        }

        return $labels;
    }
}
