<?php

namespace Cognesy\Experimental\Module\Modules\Code\Data;

class FunctionInfo
{
    public function __construct(
        public CodeInfo $codeInfo,
        public string $package = '',
        public string $class = '',
        public string $comments = '',
        public string $body = '',
    ) {}
}
