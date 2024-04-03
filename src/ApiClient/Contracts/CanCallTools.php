<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

interface CanCallTools extends CanCallApi
{
    public function toolsCall(array $messages, array $tools, array $toolChoice, string $model = '', array $options = []): static;
}