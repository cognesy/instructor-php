<?php

namespace Cognesy\Aux\Web\Contracts;

interface CanConvertToMarkdown
{
    public function toMarkdown(string $html) : string;
}
