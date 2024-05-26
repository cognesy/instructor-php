<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Signature\StructureSignature;
use Cognesy\Instructor\Extras\Structure\Structure;

trait CreatesFromClasses
{
    static public function fromClasses(
        string|object $input,
        string|object $output
    ): Signature {
        $signature = new StructureSignature(
            inputs: self::makeSignatureFromClass($input),
            outputs: self::makeSignatureFromClass($output),
        );
        return $signature;
    }

    static protected function makeSignatureFromClass(string|object $class): Structure {
        $class = is_string($class) ? $class : get_class($class);
        return Structure::fromClass($class);
    }

    public function withInputClass(string|object $input): static {
        $this->inputs = self::makeSignatureFromClass($input);
        return $this;
    }

    public function withOutputClass(string|object $output): static {
        $this->outputs = self::makeSignatureFromClass($output);
        return $this;
    }
}