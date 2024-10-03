<?php

namespace Cognesy\Instructor\Extras\Codebase\Actions;

use Cognesy\Instructor\Extras\Codebase\Data\CodeFile;
use Cognesy\Instructor\Extras\Codebase\Enums\CodeFileType;

class ExtractFiles
{
    private CodeFileType $type;

    public function __construct(CodeFileType $type) {
        $this->type = $type;
    }

    public function __invoke(string $path) : array {
        $list = [];
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $list[] = new CodeFile($path, $file, $this->type);
        }
        return $list;
    }
}
