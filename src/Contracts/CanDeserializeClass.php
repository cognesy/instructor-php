<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserializeClass
{
    public function fromJson(string $jsonData, string $dataClass) : object;
}