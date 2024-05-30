<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\Signature\StructureSignature;

trait CreatesFromClasses
{
    static public function fromClasses(
        string|object $input,
        string|object $output
    ): HasSignature {
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