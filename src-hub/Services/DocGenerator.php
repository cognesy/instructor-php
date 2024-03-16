<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Utils\CliMarkdown;

class DocGenerator
{
    public function __construct(
        private ExampleRepository $examples,
    ) {}

    public function copyAsMd(mixed $file) : void {
        throw new \Exception('Not implemented');
    }
}
