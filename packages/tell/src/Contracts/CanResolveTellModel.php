<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\TellRequest;

interface CanResolveTellModel
{
    public function resolve(TellRequest $request): LLMConfig;
}
