<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;

interface CanMaterializeRequest
{
    public function toMessages(StructuredOutputExecution $execution): array;
}