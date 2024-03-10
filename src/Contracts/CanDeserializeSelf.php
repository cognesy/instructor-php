<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserializeSelf
{
    public function fromJson(string $jsonData) : static;
}