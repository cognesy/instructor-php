<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Actions;

use Cognesy\Auxiliary\Codebase\Data\CodeFile;
use Cognesy\Auxiliary\Codebase\Enums\CodeFileType;

class ExtractFiles
{
    private CodeFileType $type;

    public function __construct(CodeFileType $type) {
        $this->type = $type;
    }

    public function __invoke(string $path) : array {
        $list = [];
        $files = scandir($path);
        if ($files === false) {
            return $list;
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $list[] = new CodeFile($path, $file, $this->type);
        }
        return $list;
    }
}
