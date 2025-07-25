<?php
namespace Cognesy\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Reflection\ClassInfo;

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
            description: ClassInfo::fromString($outputClass)->getClassDescription(),
        );
        return $signature;
    }
}