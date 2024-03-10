<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserializeDataClass
{
    public function deserialize(string $jsonData, string $dataClass) : object;
}