<?php
namespace Cognesy\Instructor\Deserializers\Symfony;

use Cognesy\Instructor\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Exceptions\DeserializationException;
use Cognesy\Instructor\Validation\Symfony\BackedEnumNormalizer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Deserializer implements CanDeserializeClass
{
    public function fromJson(string $jsonData, string $dataClass): object
    {
        // Initialize PhpDocExtractor
        $phpDocExtractor = new PhpDocExtractor();

        // Create a PropertyInfoExtractor with PhpDocExtractor for type extraction
        $typeExtractor = new PropertyInfoExtractor(
            [new ReflectionExtractor()],
            [$phpDocExtractor, new ReflectionExtractor()],
            [$phpDocExtractor],
            [$phpDocExtractor]
        );

        // Initialize the Serializer with ObjectNormalizer configured to use the type extractor
        $serializer = new Serializer(
            normalizers: [
                new BackedEnumNormalizer(),
                new ArrayDenormalizer(),
                new ObjectNormalizer(null, null, null, $typeExtractor),
            ],
            encoders: [new JsonEncoder()]
        );

        try {
            return $serializer->deserialize($jsonData, $dataClass, 'json');
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $dataClass, $jsonData);
        }
    }
}
