<?php

namespace Cognesy\Instructor\Extras\Codebase\Data;

use Cognesy\Instructor\Extras\Codebase\Enums\CodeFileType;
use Cognesy\Instructor\Utils\Uuid;

class CodeFile
{
    readonly public string $uuid;

    public function __construct(
        readonly public string $path = '',
        readonly public string $file = '',
        readonly public CodeFileType $type = CodeFileType::Other,
    ) {
        $this->uuid = Uuid::uuid4();
    }

    public function getContent(): string {
        return file_get_contents($this->path . '/' . $this->file);
    }
}
