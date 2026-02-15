<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Closure;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Context\CanCompileMessages;

final readonly class UseContextCompilerDecorator implements CanProvideAgentCapability
{
    /** @var Closure(CanCompileMessages): CanCompileMessages */
    private Closure $decorator;

    /** @param callable(CanCompileMessages): CanCompileMessages $decorator */
    public function __construct(
        callable $decorator,
    ) {
        $this->decorator = Closure::fromCallable($decorator);
    }

    #[\Override]
    public static function capabilityName(): string {
        return 'use_context_compiler_decorator';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withContextCompiler(
            ($this->decorator)($agent->contextCompiler())
        );
    }
}
