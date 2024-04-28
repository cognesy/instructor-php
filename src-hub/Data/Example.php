<?php

namespace Cognesy\InstructorHub\Data;

class Example
{
    public function __construct(
        public int    $index,
        public string $group,
        public string $name,
        public bool   $hasTitle,
        public string $title,
        public string $content,
        public string $directory,
        public string $relativePath,
        public string $runPath,
    ) {}
}