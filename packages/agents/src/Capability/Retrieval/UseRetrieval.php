<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\Retrieval\Contracts\CanReadRetrievalEvidence;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Contracts\CanRetrieveText;
use Override;

/** @implements CanProvideAgentCapability<CanConfigureAgent> */
final readonly class UseRetrieval implements CanProvideAgentCapability
{
    public function __construct(
        private CanRetrieveText $retriever,
        private ?CanReadRetrievalEvidence $reader = null,
        private ?ContextAssembler $assembler = null,
        private ?RetrievalToolPolicy $policy = null,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_retrieval';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $policy = $this->policy ?? new RetrievalToolPolicy();
        $tools = [new RetrievalSearchTool(
            $this->retriever,
            $this->assembler ?? new ContextAssembler(),
            $policy,
        )];
        if ($this->reader !== null) {
            $tools[] = new RetrievalReadTool($this->reader, $policy);
        }
        return $agent->withTools($agent->tools()->merge(new Tools(...$tools)));
    }
}
