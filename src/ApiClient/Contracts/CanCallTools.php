<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

interface CanCallTools extends CanCallApi
{
    public function toolsCall(array $messages, string $model, array $tools, array $toolChoice, array $options = []): static;
}