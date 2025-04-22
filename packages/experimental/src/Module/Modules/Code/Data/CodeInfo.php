<?php

namespace Cognesy\Experimental\Module\Modules\Code\Data;

class CodeInfo
{
    public function __construct(
        public string $name = '',
        public string $fqName = '',
        public string $sourcePath = '',
        public string $hash = '',
        public string $summary = '',
    ) {}
}
