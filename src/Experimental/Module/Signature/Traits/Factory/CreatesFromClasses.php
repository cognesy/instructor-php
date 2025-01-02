<?php
namespace Cognesy\Instructor\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Utils\ClassInfo;

trait CreatesFromClasses
{
    static public function fromClasses(
        string|object $input,
        string|object $output
    ): Signature {
        $outputClass = is_string($output) ? $output : get_class($output);
        $signature = new Signature(
            input: (new SchemaFactory)->schema($input),
            output: (new SchemaFactory)->schema($output),
            description: (new ClassInfo($outputClass))->getClassDescription(),
        );
        return $signature;
    }
}