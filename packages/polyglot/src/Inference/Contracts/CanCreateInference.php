<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\PendingInference;

interface CanCreateInference
{
    public function create(InferenceRequest $request): PendingInference;
}
