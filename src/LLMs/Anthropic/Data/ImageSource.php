<?php
namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class ImageSource
{
    public function __construct(
        public string $type,
        public string $mediaType,
        public string $data,
    ) {}
}