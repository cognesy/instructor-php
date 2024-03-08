<?php

namespace Cognesy\Instructor\Schema\Data;

class Reference
{
    public function __construct(
        public string $id = '',
        public string $class = '',
        public string $classShort = '',
        public bool   $isRendered = false,
    ) {}
}