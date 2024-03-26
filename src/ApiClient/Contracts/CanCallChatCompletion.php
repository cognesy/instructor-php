<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

interface CanCallChatCompletion extends CanCallApi
{
    public function chatCompletion(array $messages, string $model, array $options = []): static;
}