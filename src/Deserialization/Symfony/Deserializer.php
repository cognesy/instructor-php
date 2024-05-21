<?php
namespace Cognesy\Instructor\Deserialization\Symfony;

use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Exceptions\DeserializationException;
use Cognesy\Instructor\Utils\Json;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class Deserializer implements CanDeserializeClass
{
    public function __construct(
        protected ?PropertyInfoExtractor $typeExtractor = null,
        protected ?Serializer $serializer = null,
    ) {
        // Create a PropertyInfoExtractor for type extraction
        $this->typeExtractor = $typeExtractor ?? $this->defaultTypeExtractor();
        // Initialize the Serializer with normalizers configured to use the type extractor
        $this->serializer = $serializer ?? $this->defaultSerializer($this->typeExtractor);
    }

    public function fromJson(string $jsonData, string $dataType): mixed {
        return match($dataType) {
            default => $this->deserializeObject($this->serializer, $jsonData, $dataType)
        };
    }

    public function fromArray(array $data, string $dataType): mixed {
        return match($dataType) {
            default => $this->denormalizeObject($this->serializer, $data, $dataType)
        };
    }

    public function toArray(object $object): array {
        $normalized = $this->serializer->normalize($object, 'array');
        return match(true) {
            ($normalized === null) => [],
            is_array($normalized) => $normalized,
            is_object($normalized) => (array) $normalized,
            is_string($normalized) => ['value' => $normalized], // TODO: find better way
            default => $normalized
        };
    }

    protected function defaultTypeExtractor() : PropertyInfoExtractor {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        return new PropertyInfoExtractor(
            listExtractors: [$reflectionExtractor],
            typeExtractors: [new PhpStanExtractor(), $phpDocExtractor, $reflectionExtractor],
            descriptionExtractors: [$phpDocExtractor],
            accessExtractors: [$reflectionExtractor],
            initializableExtractors: [$reflectionExtractor],
        );
    }

    protected function defaultSerializer(PropertyInfoExtractor $typeExtractor) : Serializer {
        return new Serializer(
            normalizers: [
                new BackedEnumNormalizer(),
                new DateTimeNormalizer(),
                new PropertyNormalizer(propertyTypeExtractor: $typeExtractor),
                new ObjectNormalizer(propertyTypeExtractor: $typeExtractor),
                new GetSetMethodNormalizer(propertyTypeExtractor: $typeExtractor),
                new ArrayDenormalizer(),
            ],
            encoders: [new JsonEncoder()]
        );
    }

    protected function deserializeObject(
        Serializer $serializer,
        string $jsonData,
        string $dataClass
    ) : object {
        try {
            return $serializer->deserialize($jsonData, $dataClass, 'json');
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $dataClass, $jsonData);
        }
    }

    protected function denormalizeObject(
        Serializer $serializer,
        array $data,
        string $dataClass
    ) : object {
        try {
            return $serializer->denormalize($data, $dataClass);
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $dataClass, Json::encode($data));
        }
    }
}
