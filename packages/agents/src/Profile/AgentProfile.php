<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Utils\Metadata;

final readonly class AgentProfile
{
    public function __construct(
        public AgentIdentity $identity,
        public ToolProfileList $tools,
        public CapabilityProfileList $capabilities,
        public HookProfileList $hooks,
        public ?string $driverClass = null,
        public ?LLMConfigProfile $llm = null,
        public Metadata $metadata = new Metadata(),
        private AgentProfileContributors $contributors = new AgentProfileContributors(),
    ) {}

    public static function empty(): self {
        return new self(
            identity: AgentIdentity::anonymous(),
            tools: new ToolProfileList(),
            capabilities: CapabilityProfileList::empty(),
            hooks: new HookProfileList(),
        );
    }

    public static function fromResolved(
        AgentIdentity $identity,
        Tools $tools,
        CapabilityProfileList $capabilities,
        CanUseTools $driver,
        CanInterceptAgentLifecycle $interceptor,
        ?AgentProfileContributors $contributors = null,
        ?Metadata $metadata = null,
        ?NameList $deferredToolNames = null,
    ): self {
        $profile = new self(
            identity: $identity,
            tools: ToolProfileList::fromTools($tools, $deferredToolNames),
            capabilities: $capabilities,
            hooks: HookProfileList::fromInterceptor($interceptor),
            driverClass: $driver::class,
            llm: self::llmProfile($driver),
            metadata: $metadata ?? Metadata::empty(),
            contributors: $contributors ?? AgentProfileContributors::empty(),
        );
        return $profile->refreshContributions();
    }

    public function name(): string {
        return $this->identity->name;
    }

    public function description(): string {
        return $this->identity->description;
    }

    public function withMetadata(Metadata $metadata): self {
        return new self(
            identity: $this->identity,
            tools: $this->tools,
            capabilities: $this->capabilities,
            hooks: $this->hooks,
            driverClass: $this->driverClass,
            llm: $this->llm,
            metadata: $metadata,
            contributors: $this->contributors,
        );
    }

    public function withRuntime(
        Tools $tools,
        CanUseTools $driver,
        CanInterceptAgentLifecycle $interceptor,
    ): self {
        $profile = new self(
            identity: $this->identity,
            tools: ToolProfileList::fromTools($tools, $this->tools->deferredNames()),
            capabilities: $this->capabilities,
            hooks: HookProfileList::fromInterceptor($interceptor),
            driverClass: $driver::class,
            llm: self::llmProfile($driver),
            metadata: $this->metadata,
            contributors: $this->contributors,
        );
        return $profile->refreshContributions();
    }

    private function refreshContributions(): self {
        return $this->contributors->contribute($this);
    }

    private static function llmProfile(CanUseTools $driver): ?LLMConfigProfile {
        return match (true) {
            $driver instanceof CanResolveLLMConfig => LLMConfigProfile::fromConfig($driver->resolveConfig()),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            ...$this->identity->toArray(),
            'tools' => $this->tools->toArray(),
            'capabilities' => $this->capabilities->toArray(),
            'hooks' => $this->hooks->toArray(),
            'driverClass' => $this->driverClass,
            'llm' => $this->llm?->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
