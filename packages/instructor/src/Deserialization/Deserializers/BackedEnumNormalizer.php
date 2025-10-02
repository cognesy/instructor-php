<?php declare(strict_types=1);

namespace Cognesy\Instructor\Deserialization\Deserializers;

use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes a {@see \BackedEnum} enumeration to a string or an integer.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 * @author Dariusz Debowczyk
 */
final class BackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * If true, will denormalize any invalid value into null.
     */
    public const ALLOW_INVALID_VALUES = 'allow_invalid_values';

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            \BackedEnum::class => true,
        ];
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): int|string
    {
        if (!$data instanceof \BackedEnum) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        return $data->value;
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \BackedEnum;
    }

    /**
     * @throws NotNormalizableValueException
     */
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \BackedEnum::class)) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        if ($context[self::ALLOW_INVALID_VALUES] ?? false) {
            if (null === $data || (!\is_int($data) && !\is_string($data))) {
                return null;
            }

            try {
                return $type::tryFrom($data);
            } catch (\TypeError) {
                return null;
            }
        }

        if (!\is_int($data) && !\is_string($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is neither an integer nor a string, you should pass an integer or a string that can be parsed as an enumeration case of type '.$type.'.', $data, [Type::BUILTIN_TYPE_INT, Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true);
        }

        try {
            return $type::from($data);
        } catch (\ValueError $e) {
            $values = array_map(fn($case) => $case->value, (new \ReflectionEnum($type))->getConstants());
            $optionsStr = implode(', ', $values);
            throw new InvalidArgumentException('The value must one of: '.$optionsStr, 0, $e);
        }
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, \BackedEnum::class);
    }
}
