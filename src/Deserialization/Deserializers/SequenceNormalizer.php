<?php

namespace Cognesy\Instructor\Deserialization\Deserializers;

use Cognesy\Instructor\Extras\Sequence\Sequence;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SequenceNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array {
        return [
            Sequence::class => true,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool {
        return $data instanceof Sequence;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool {
        return is_subclass_of($type, Sequence::class);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|\ArrayObject|null {
        if (!$object instanceof Sequence) {
            throw new InvalidArgumentException('The data must belong to a sequence.');
        }
        $normalized = [];
        foreach ($object->list as $item) {
            $normalized[] = match(true) {
                is_array($item) => $item,
                is_object($item) => $this->normalize($item, $format, $context),
                default => $item
            };
        }
        return $normalized;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        // TODO: Implement denormalize() method.
    }
}