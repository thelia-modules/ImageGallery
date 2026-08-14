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

namespace ImageGallery\Form;

use ImageGallery\ImageGallery;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Fields shared by the slide creation and edition forms: both describe the same
 * slide, so their common fields must stay defined in a single place (same name,
 * same constraints, same messages, same labels).
 */
abstract class AbstractImageGalleryForm extends BaseForm
{
    protected function addTitleField(): void
    {
        $this->formBuilder->add('title', TextType::class, [
            'constraints' => [new NotBlank()],
            'label' => Translator::getInstance()->trans('Title', [], ImageGallery::DOMAIN_NAME),
        ]);
    }

    /**
     * @param string|null $defaultGroup pre-filled group name, null to leave the field empty
     */
    protected function addGroupField(?string $defaultGroup = null): void
    {
        $translator = Translator::getInstance();

        $options = [
            'constraints' => [
                new NotBlank(),
                new Regex(
                    pattern: '/^'.ImageGallery::GROUP_PATTERN.'$/',
                    message: $translator->trans('Use only letters, digits, hyphens and underscores (max 64).', [], ImageGallery::DOMAIN_NAME),
                ),
            ],
            'label' => $translator->trans('Group image', [], ImageGallery::DOMAIN_NAME),
        ];

        if ($defaultGroup !== null) {
            $options['data'] = $defaultGroup;
        }

        $this->formBuilder->add('group', TextType::class, $options);
    }
}
