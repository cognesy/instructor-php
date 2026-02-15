<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Context\CanCompileMessages;

final readonly class UseContextCompiler implements CanProvideAgentCapability
{
    public function __construct(
        private CanCompileMessages $compiler,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_context_compiler';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withContextCompiler($this->compiler);
    }
}
