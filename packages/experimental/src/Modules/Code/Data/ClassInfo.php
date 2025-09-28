<?php

namespace Cognesy\Experimental\Modules\Code\Data;

class ClassInfo
{
    public function __construct(
        public CodeInfo $codeInfo,
        public string $package = '',
        public string $comments = '',
        public string $body = '',
    ) {}
}
