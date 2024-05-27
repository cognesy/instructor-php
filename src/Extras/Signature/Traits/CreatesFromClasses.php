<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait CreatesFromClasses
{
    static public function fromClasses(
        string|object $input,
        string|object $output
    ): Signature {
        $signature = new StructureSignature(
            inputs: self::makeStructureFromClass($input),
            outputs: self::makeStructureFromClass($output),
        );
        return $signature;
    }

    static protected function makeStructureFromClass(string|object $class): Structure {
        $class = is_string($class) ? $class : get_class($class);
        return StructureFactory::fromClass($class);
    }
}