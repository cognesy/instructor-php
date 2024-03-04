<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Tag;

class TypeDefTag
{
    public function __construct(
        public string $tag,
        public string $name,
        public string $type,
        public string $description
    ) {}
}
