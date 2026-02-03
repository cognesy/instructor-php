<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Registry;

use Cognesy\Addons\AgentTemplate\Spec\AgentSpec;

/** Immutable result of loading agent specs — carries both successes and errors. */
final readonly class AgentSpecLoadResult
{
    /**
     * @param array<string, AgentSpec> $specs   Keyed by agent name
     * @param array<string, string>    $errors  Keyed by file path
     */
    public function __construct(
        public array $specs = [],
        public array $errors = [],
    ) {}
}
