<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Symfony\Component\Serializer\Attribute\Ignore;

trait CreatesFromClassMetadata
{
    static private array $internalProperties = [
        'inputs',
        'outputs',
        'internal',
    ];

    static public function fromClassMetadata(string|object $class) : Signature {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $classInfo = new ClassInfo($class);
        $classDescription = $classInfo->getClassDescription();
        $fields = self::getFields($classInfo);
        return new Signature(
            inputs: Structure::define('inputs', $fields['inputs']),
            outputs: Structure::define('outputs', $fields['outputs']),
            description: $classDescription,
        );
    }

    /**
     * @param ClassInfo $classInfo
     * @return array<string, Field[]>
     */
    static private function getFields(ClassInfo $classInfo) : array {
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
                $property->isReadOnly() => 'inputs',
                default => null,
            };
            // add field to group
            $type = $property->getType();
            $description = $property->getDescription();
            $isOptional = $property->isNullable();
            $fields[$group][] = Field::fromPropertyInfoType($name, $type, $description)->optional($isOptional);
        }
        return $fields;
    }
}
