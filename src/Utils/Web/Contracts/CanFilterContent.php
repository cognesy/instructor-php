<?php

namespace Cognesy\Instructor\Utils\Web\Contracts;

interface CanFilterContent
{
    public function filter(string $content) : bool;
}