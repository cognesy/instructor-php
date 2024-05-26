<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait CreatesFromClassMetadata
{
    use GetsFieldsFromClass;

    static public function fromClassMetadata(string|object $class) : Signature {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $classInfo = new ClassInfo($class);
        $classDescription = $classInfo->getClassDescription();
        $fields = self::getFields($classInfo);
        return new StructureSignature(
            inputs: Structure::define('inputs', $fields['inputs']),
            outputs: Structure::define('outputs', $fields['outputs']),
            description: $classDescription,
        );
    }
}
