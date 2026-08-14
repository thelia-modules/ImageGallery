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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;

class ImageGalleryCreateForm extends AbstractImageGalleryForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('file', FileType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Image(mimeTypes: ImageGallery::ALLOWED_IMAGE_MIME_TYPES),
                ],
                'label' => $translator->trans('Desktop image', [], ImageGallery::DOMAIN_NAME),
            ])
            ->add('mobile_file', FileType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Image(mimeTypes: ImageGallery::ALLOWED_IMAGE_MIME_TYPES),
                ],
                'label' => $translator->trans('Mobile image', [], ImageGallery::DOMAIN_NAME),
            ]);

        $this->addTitleField();
        $this->addGroupField('home');
    }

    public static function getName(): string
    {
        return 'image_gallery_create';
    }
}
