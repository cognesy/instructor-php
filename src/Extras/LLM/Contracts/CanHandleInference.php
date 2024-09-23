<?php
namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Cognesy\Instructor\Extras\LLM\InferenceRequest;
use Psr\Http\Message\ResponseInterface;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : ResponseInterface;
}
