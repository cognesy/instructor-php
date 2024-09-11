<?php

namespace Cognesy\Instructor\Extras\Web\Contracts;

interface CanConvertToMarkdown
{
    public function toMarkdown(string $html) : string;
}
