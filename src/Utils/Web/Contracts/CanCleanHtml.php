<?php

namespace Cognesy\Instructor\Utils\Web\Contracts;

interface CanCleanHtml
{
    public function process(string $html): string;
}