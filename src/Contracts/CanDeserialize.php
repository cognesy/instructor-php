<?php

namespace Cognesy\Instructor\Contracts;

interface CanDeserialize
{
    public function deserialize(string $data, string $model) : object;
}