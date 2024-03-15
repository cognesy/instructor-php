<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Utils\CliMarkdown;

class DocGenerator
{
    private string $baseDir = '';
    private CliMarkdown $parser;

    public function __construct(
        private Examples $examples,
    ) {
        $this->baseDir = $this->examples->getBaseDir();
        $this->parser = new CliMarkdown();
    }

    public function copyAsMd(mixed $file) : void {
        $output = file_get_contents($this->baseDir . '/' . $file . '/run.php');
        throw new \Exception('Not implemented');
    }
}
