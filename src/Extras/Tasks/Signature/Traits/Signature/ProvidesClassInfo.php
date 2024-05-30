<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits\Signature;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Schema\Utils\PropertyInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait ProvidesClassInfo
{
    static private array $internalProperties = [
        'inputs',
        'outputs',
        'internal',
    ];

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<string>
     */
    static protected function getPropertyNames(ClassInfo $classInfo, array $filters) : array {
        return array_keys(self::getFilteredPropertyData(
            classInfo: $classInfo,
            filters: array_merge([self::defaultExclusionsFilter(...)], $filters),
            extractor: fn(PropertyInfo $property) => $property->getName()
        ));
    }

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<PropertyInfo>
     */
    static protected function getProperties(ClassInfo $classInfo, array $filters) : array {
        return self::getFilteredPropertyData(
            classInfo: $classInfo,
            filters: array_merge([self::defaultExclusionsFilter(...)], $filters),
            extractor: fn(PropertyInfo $property) => $property
        );
    }

    /**
     * @param ClassInfo $classInfo
     * @return array<string, PropertyInfo>
     */
    static private function getFilteredPropertyData(ClassInfo $classInfo, array $filters, callable $extractor) : array {
        return array_map(
            callback: fn(PropertyInfo $property) => $extractor($property),
            array: $classInfo->filterProperties($filters),
        );
    }

    static private function defaultExclusionsFilter(PropertyInfo $property) : bool {
        return match(true) {
            in_array($property->getName(), self::$internalProperties) => false,
            $property->hasAttribute(Ignore::class) => false,
            $property->isStatic() => false,
            default => true,
        };
    }

    // DEPRECATED /////////////////////////////////////////////////////////////////////

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<Field>
     */
    static protected function getFields(ClassInfo $classInfo, array $filters) : array {
        return self::getFilteredPropertyData(
            classInfo: $classInfo,
            filters: array_merge([self::defaultExclusionsFilter(...)], $filters),
            extractor: self::fieldExtractor(...),
        );
    }

    static private function fieldExtractor(PropertyInfo $property) : Field {
        $name = $property->getName();
        $type = $property->getType();
        $description = $property->getDescription();
        $isOptional = $property->isNullable();
        return FieldFactory::fromPropertyInfoType($name, $type, $description)->optional($isOptional);
    }
}
