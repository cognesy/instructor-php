<?php

namespace Cognesy\Instructor\Extras\Web\Contracts;

interface CanFilterContent
{
    public function filter(string $content) : bool;
}