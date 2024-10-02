<?php

namespace Cognesy\Instructor\Extras\Codebase\Actions;

use Cognesy\Instructor\Extras\Codebase\Data\CodeFile;
use Cognesy\Instructor\Extras\Codebase\Enums\CodeFileType;

class ExtractFiles
{
    public function __invoke(string $path) : array {
        $list = [];
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $list[] = new CodeFile($path, $file, CodeFileType::Other);
        }
        return $list;
    }
}
