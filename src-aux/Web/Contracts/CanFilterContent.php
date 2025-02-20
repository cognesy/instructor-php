<?php

namespace Cognesy\Aux\Web\Contracts;

interface CanFilterContent
{
    public function filter(string $content) : bool;
}