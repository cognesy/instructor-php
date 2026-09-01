<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Model;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Data\TellRequest;

interface CanResolveTellModel
{
    public function resolve(TellRequest $request): LLMConfig;
}
