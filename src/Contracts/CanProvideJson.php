<?php

namespace Cognesy\Instructor\Contracts;

interface CanProvideJson
{
    public function toJson(): string;
}