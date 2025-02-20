<?php

namespace Cognesy\Aux\Web\Contracts;

interface CanCleanHtml
{
    public function process(string $html): string;
}