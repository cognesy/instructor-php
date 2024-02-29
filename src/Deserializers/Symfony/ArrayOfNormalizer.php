<?php

namespace Cognesy\Instructor\Deserializers\Symfony;

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Cognesy\Instructor\Attributes\ArrayOf;

class CustomObjectNormalizer extends ObjectNormalizer
{
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        $reflectionProperty = new \ReflectionProperty($object, $attribute);
        $arrayOfAttribute = $reflectionProperty->getAttributes(ArrayOf::class)[0] ?? null;

        if ($arrayOfAttribute) {
            $attributeType = $arrayOfAttribute->newInstance()->type;
            if (is_array($value)) {
                $value = array_map(function ($item) use ($attributeType) {
                    return $this->serializer->denormalize($item, $attributeType);
                }, $value);
            }
        }

        parent::setAttributeValue($object, $attribute, $value, $format, $context);
    }
}
