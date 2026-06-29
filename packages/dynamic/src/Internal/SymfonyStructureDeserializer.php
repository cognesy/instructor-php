<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Internal;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Default array->object deserializer used by {@see StructureValueNormalizer} when a
 * Structure field is typed as a concrete (non-Structure) class.
 *
 * This is a self-contained Symfony-Serializer-based implementation of
 * {@see CanDeserializeClass}. It lives in the `dynamic` package (rather than reusing
 * `instructor-struct`'s SymfonyDeserializer) so that `dynamic` does not depend on
 * `instructor-struct` — which would re-introduce the dynamic <-> instructor-struct
 * dependency cycle. It relies on Symfony's built-in enum/date normalizers, which cover
 * the cases the normalizer's best-effort fallback needs.
 */
final class SymfonyStructureDeserializer implements CanDeserializeClass
{
    private ?Serializer $serializer = null;

    /**
     * @template T of object
     * @param array<string,mixed> $data
     * @param class-string<T> $dataType
     * @return T
     */
    public function fromArray(array $data, string $dataType): mixed {
        return $this->serializer()->denormalize($data, $dataType);
    }

    private function serializer(): Serializer {
        if ($this->serializer === null) {
            $this->serializer = $this->defaultSerializer();
        }
        return $this->serializer;
    }

    private function defaultSerializer(): Serializer {
        $typeExtractor = new PropertyInfoExtractor(
            listExtractors: [new ReflectionExtractor()],
            typeExtractors: [new PhpStanExtractor(), new PhpDocExtractor(), new ReflectionExtractor()],
            accessExtractors: [new ReflectionExtractor()],
            initializableExtractors: [new ReflectionExtractor()],
        );
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        return new Serializer(
            normalizers: [
                new DateTimeNormalizer(),
                new BackedEnumNormalizer(),
                new ObjectNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyAccessor: $propertyAccessor,
                    propertyTypeExtractor: $typeExtractor,
                ),
                new PropertyNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyTypeExtractor: $typeExtractor,
                ),
                new GetSetMethodNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyTypeExtractor: $typeExtractor,
                ),
                new ArrayDenormalizer(),
            ],
            encoders: [],
        );
    }
}
