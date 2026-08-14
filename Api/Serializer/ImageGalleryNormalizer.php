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

namespace ImageGallery\Api\Serializer;

use ImageGallery\Api\Resource\ImageGallery;
use ImageGallery\Service\ImageGalleryImageProcessor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Enriches the ImageGallery resource with processed image URLs so API consumers
 * never have to know the upload directory or the image cache layout.
 * Optional `width`/`height` query parameters resize both variants.
 *
 * Decorates the normalizer chain instead of replacing it: the whole chain is
 * replayed once the ALREADY_CALLED flag is set, so the format-specific
 * normalizer (JSON-LD `@id`/`@type`/`@context`, HAL…) still does its job and
 * only then are the computed image fields merged into its output.
 */
final class ImageGalleryNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const MAX_DIMENSION = 4000;

    /** Marks the context of a normalization this normalizer already handled. */
    private const ALREADY_CALLED = 'IMAGE_GALLERY_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly ImageGalleryImageProcessor $imageProcessor,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!\is_array($data)) {
            return $data;
        }

        /** @var ImageGallery $gallery */
        $gallery = $object;

        $request = $this->requestStack->getCurrentRequest();
        $width = $this->clampDimension($request?->query->get('width'));
        $height = $this->clampDimension($request?->query->get('height'));

        // Same fields, same srcset rule as the presenter: both read them from the
        // image processor instead of restating the rule.
        return [
            ...$data,
            ...$this->imageProcessor->imageFields($gallery->getFile(), $gallery->getMobileFile(), $width, $height),
        ];
    }

    /**
     * Bounds a client-provided dimension: null when absent or invalid, capped at
     * MAX_DIMENSION otherwise. Prevents unbounded image generation (CPU/disk DoS)
     * through arbitrarily large `width`/`height` query parameters.
     */
    private function clampDimension(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $dimension = (int) $value;

        return $dimension > 0 ? min($dimension, self::MAX_DIMENSION) : null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Every operation on this resource is a read: always enrich — but only
        // once, otherwise delegating to the chain would loop back here.
        return $data instanceof ImageGallery && !isset($context[self::ALREADY_CALLED]);
    }

    /** Not cacheable: support depends on the context (see ALREADY_CALLED). */
    public function getSupportedTypes(?string $format): array
    {
        return [
            ImageGallery::class => false,
        ];
    }
}
