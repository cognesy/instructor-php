<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Code\Data;

class ClassInfo
{
    public function __construct(
        public CodeInfo $codeInfo,
        public string $package = '',
        public string $comments = '',
        public string $body = '',
    ) {}
}
