<?php
namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class ImageContent
{
    public string $type = 'image';

    public function __construct(
        public ImageSource $source,
    ) {}
}