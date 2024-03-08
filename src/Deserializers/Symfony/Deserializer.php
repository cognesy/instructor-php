<?php
namespace Cognesy\Instructor\Deserializers\Symfony;

use Cognesy\Instructor\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Validators\Symfony\BackedEnumNormalizer;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Deserializer implements CanDeserializeResponse
{
    public function deserialize(string $data, string $dataModelClass): object
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

        return $serializer->deserialize($data, $dataModelClass, 'json');
    }
}
