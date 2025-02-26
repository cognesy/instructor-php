<?php

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanConvertToMarkdown
{
    public function toMarkdown(string $html) : string;
}
