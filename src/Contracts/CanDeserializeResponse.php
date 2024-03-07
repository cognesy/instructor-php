<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserializeResponse
{
    public function deserialize(string $data, string $dataModelClass) : object;
}