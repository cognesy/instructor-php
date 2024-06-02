<?php
namespace Cognesy\Instructor\Extras\Module\CallData\Traits\Factory;

use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Extras\Module\CallData\CallData;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\StructureFactory;

trait CreatesFromClasses
{
    static public function fromClasses(
        string|object $input,
        string|object $output
    ): HasInputOutputData {
        $callData = new CallData(
            input: self::makeStructureFromClass($input),
            output: self::makeStructureFromClass($output),
            signature: SignatureFactory::fromClasses($input, $output)
        );
        return $callData;
    }

    static protected function makeStructureFromClass(string|object $class): Structure {
        $class = is_string($class) ? $class : get_class($class);
        return StructureFactory::fromClass($class);
    }
}