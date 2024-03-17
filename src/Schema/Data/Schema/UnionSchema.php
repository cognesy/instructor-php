<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

class UnionSchema
{
    public function __construct(
        public array $schemas,
    ) {}
}