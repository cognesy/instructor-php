<?php

namespace Cognesy\Instructor\Utils\Web\Contracts;

interface CanConvertToMarkdown
{
    public function toMarkdown(string $html) : string;
}
