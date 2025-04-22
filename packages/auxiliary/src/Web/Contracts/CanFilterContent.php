<?php

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanFilterContent
{
    public function filter(string $content) : bool;
}