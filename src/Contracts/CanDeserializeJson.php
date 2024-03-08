<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserializeJson
{
    public function fromJson(string $json) : static;
}