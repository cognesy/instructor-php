<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Data;

use Cognesy\Auxiliary\Codebase\Enums\CodeFileType;
use Cognesy\Utils\Uuid;

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
        $content = file_get_contents($this->path . '/' . $this->file);
        return is_string($content) ? $content : '';
    }
}
