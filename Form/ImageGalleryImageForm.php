<?php

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
use Thelia\Form\BaseForm;

/**
 * Class ImageGalleryImageForm.
 *
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ImageGalleryImageForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();
        $this->formBuilder
            ->add(
                'file',
                FileType::class,
                [
                    'constraints' => [
                        // NotBlank is required: the Image constraint is skipped on a
                        // null value, so a submit without a file would reach the
                        // upload service with no file at all.
                        new NotBlank(),
                        new Image(mimeTypes: ImageGallery::ALLOWED_IMAGE_MIME_TYPES),
                    ],
                    'label' => $translator->trans('ImageGallery image', [], ImageGallery::DOMAIN_NAME),
                    'label_attr' => [
                        'for' => 'file',
                    ],
                ]
            );
    }
}
