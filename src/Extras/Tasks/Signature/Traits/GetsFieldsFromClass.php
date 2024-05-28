<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait GetsFieldsFromClass
{
    static private array $internalProperties = [
        'inputs',
        'outputs',
        'internal',
    ];

    /**
     * @param ClassInfo $classInfo
     * @return array<string, \Cognesy\Instructor\Extras\Structure\Field[]>
     */
    static protected function getFields(ClassInfo $classInfo) : array {
        $fields = [
            'inputs' => [],
            'outputs' => [],
        ];
        foreach ($classInfo->getProperties() as $name => $property) {
            $skipProperty = match(true) {
                in_array($name, self::$internalProperties) => true,
                $property->hasAttribute(Ignore::class) => true,
                $property->isStatic() => true,
                default => false,
            };
            if ($skipProperty) {
                continue;
            }
            $group = match(true) {
                $property->hasAttribute(InputField::class) => 'inputs',
                $property->hasAttribute(OutputField::class) => 'outputs',
                //$property->isReadOnly() => 'inputs',
                default => 'excluded',
            };
            // add field to group
            $type = $property->getType();
            $description = implode(". ", array_filter(array_merge(...[
                [$property->getDescription()],
                $property->getAttributeValues(InputField::class, 'description'),
                $property->getAttributeValues(OutputField::class, 'description'),
            ])));
            $isOptional = $property->isNullable();
            $fields[$group][] = FieldFactory::fromPropertyInfoType($name, $type, $description)->optional($isOptional);
        }
        return $fields;
    }
}