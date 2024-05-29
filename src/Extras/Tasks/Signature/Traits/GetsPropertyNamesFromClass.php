<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits;

use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait GetsPropertyNamesFromClass
{
    static private array $internalProperties = [
        'inputs',
        'outputs',
        'internal',
    ];

    /**
     * @param ClassInfo $classInfo
     * @return array<string, array<string>>
     */
    static protected function getPropertyNames(ClassInfo $classInfo) : array {
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
            $fields[$group][] = $name;
        }
        return $fields;
    }
}