<?php

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanCleanHtml
{
    public function process(string $html): string;
}