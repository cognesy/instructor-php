<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Schema\Utils\PropertyInfo;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Serializer\Attribute\Ignore;

#[Deprecated]
trait ProvidesClassInfo
{
    private array $internalProperties = [
        'inputs',
        'outputs',
        'internal',
    ];

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<string>
     */
    protected function getPropertyNames(ClassInfo $classInfo, array $filters) : array {
        return array_keys($this->getFilteredPropertyData(
            classInfo: $classInfo,
            filters: array_merge([$this->defaultExclusionsFilter(...)], $filters),
            extractor: fn(PropertyInfo $property) => $property->getName()
        ));
    }

    /**
     * @param ClassInfo $classInfo
     * @param array<callable> $filters
     * @return array<PropertyInfo>
     */
    private function getProperties(ClassInfo $classInfo, array $filters) : array {
        return $this->getFilteredPropertyData(
            classInfo: $classInfo,
            filters: array_merge([$this->defaultExclusionsFilter(...)], $filters),
            extractor: fn(PropertyInfo $property) => $property
        );
    }

    /**
     * @param ClassInfo $classInfo
     * @return array<string, PropertyInfo>
     */
    private function getFilteredPropertyData(ClassInfo $classInfo, array $filters, callable $extractor) : array {
        return array_map(
            callback: fn(PropertyInfo $property) => $extractor($property),
            array: $classInfo->filterProperties($filters),
        );
    }

    private function defaultExclusionsFilter(PropertyInfo $property) : bool {
        return match(true) {
            in_array($property->getName(), $this->internalProperties) => false,
            $property->hasAttribute(Ignore::class) => false,
            $property->isStatic() => false,
            default => true,
        };
    }
}
