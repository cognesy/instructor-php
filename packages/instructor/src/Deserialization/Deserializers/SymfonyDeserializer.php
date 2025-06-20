<?php

namespace Cognesy\Instructor\Deserialization\Deserializers;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Exceptions\DeserializationException;
use Cognesy\Utils\Json\Json;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class SymfonyDeserializer implements CanDeserializeClass
{
    public function __construct(
        protected ?PropertyInfoExtractor $typeExtractor = null,
        protected ?Serializer $serializer = null,
    ) {}

    public function fromJson(string $jsonData, string $dataType): mixed {
        return match ($dataType) {
            default => $this->deserializeObject($this->serializer(), $jsonData, $dataType)
        };
    }

    public function fromArray(array $data, string $dataType): mixed {
        return match ($dataType) {
            default => $this->denormalizeObject($this->serializer(), $data, $dataType)
        };
    }

    public function toArray(object $object): array {
        $normalized = $this->serializer()->normalize($object, 'array');
        return match (true) {
            ($normalized === null) => [],
            is_array($normalized) => $normalized,
            is_object($normalized) => (array)$normalized,
            is_string($normalized) => ['value' => $normalized], // TODO: find better way
            default => $normalized
        };
    }

    protected function typeExtractor(): PropertyInfoExtractor {
        if (!isset($this->typeExtractor)) {
            $this->typeExtractor = $this->defaultTypeExtractor();
        }
        return $this->typeExtractor;
    }

    protected function defaultTypeExtractor(): PropertyInfoExtractor {
        $phpDocExtractor = new PhpDocExtractor();
        $phpStanExtractor = new PhpStanExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        return new PropertyInfoExtractor(
            listExtractors: [
                $reflectionExtractor
            ],
            typeExtractors: [
                $phpStanExtractor,
                $phpDocExtractor,
                $reflectionExtractor
            ],
            descriptionExtractors: [
                $phpDocExtractor
            ],
            accessExtractors: [
                $reflectionExtractor
            ],
            initializableExtractors: [
                $reflectionExtractor
            ],
        );
    }

    protected function serializer(): Serializer {
        if (!isset($this->serializer)) {
            $this->serializer = $this->defaultSerializer($this->typeExtractor());
        }
        return $this->serializer;
    }

    protected function defaultSerializer(PropertyInfoExtractor $typeExtractor): Serializer {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        return new Serializer(
            normalizers: [
                new FlexibleDateDenormalizer(),
                new BackedEnumNormalizer(),
                new ObjectNormalizer(
                    classMetadataFactory: $classMetadataFactory,
                    propertyAccessor: $propertyAccessor,
                    propertyTypeExtractor: $typeExtractor,
                    //propertyInfoExtractor: $typeExtractor,
                ),
                //new ObjectNormalizer(propertyTypeExtractor: $typeExtractor),
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
            encoders: [new JsonEncoder()]
        );
    }

    protected function deserializeObject(Serializer $serializer, string $jsonData, string $dataClass): object {
        try {
            return $serializer->deserialize($jsonData, $dataClass, 'json');
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $dataClass, $jsonData);
        }
    }

    protected function denormalizeObject(Serializer $serializer, array $data, string $dataClass): object {
        try {
            return $serializer->denormalize($data, $dataClass);
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $dataClass, Json::encode($data));
        }
    }
}
