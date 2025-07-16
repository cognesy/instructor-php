<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;

interface CanMapRequestBody
{
    public function toRequestBody(InferenceRequest $request) : array;
}