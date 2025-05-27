<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputRequest;

interface CanMaterializeRequest
{
    public function toMessages(StructuredOutputRequest $request): array;
}