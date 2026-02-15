<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;

interface CanCreateStructuredOutput
{
    public function create(StructuredOutputRequest $request): PendingStructuredOutput;
}
