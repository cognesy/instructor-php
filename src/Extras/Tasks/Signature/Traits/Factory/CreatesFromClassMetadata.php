<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Tasks\Signature\Traits\ProvidesClassData;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait CreatesFromClassMetadata
{
    use ProvidesClassData;

    static public function fromClassMetadata(string|object $class) : Signature {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $classInfo = new ClassInfo($class);
        $classDescription = $classInfo->getClassDescription();
        $inputFields = self::getFields($classInfo, [fn($property) => $property->hasAttribute(InputField::class)]);
        $outputFields = self::getFields($classInfo, [fn($property) => $property->hasAttribute(OutputField::class)]);
        return new StructureSignature(
            inputs: Structure::define('inputs', $inputFields),
            outputs: Structure::define('outputs', $outputFields),
            description: $classDescription,
        );
    }
}
