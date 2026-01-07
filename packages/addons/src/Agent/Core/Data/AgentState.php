<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Data;

use Cognesy\Addons\Agent\Core\Collections\AgentSteps;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Core\Traits\State\HandlesAgentSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\State\Contracts\HasMetadata;
use Cognesy\Addons\StepByStep\State\Contracts\HasStateInfo;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;
use Cognesy\Addons\StepByStep\State\StateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMessageStore;
use Cognesy\Addons\StepByStep\State\Traits\HandlesMetadata;
use Cognesy\Addons\StepByStep\State\Traits\HandlesStateInfo;
use Cognesy\Addons\StepByStep\State\Traits\HandlesUsage;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/** @implements HasSteps<AgentStep> */
final readonly class AgentState implements HasSteps, HasMessageStore, HasMetadata, HasUsage, HasStateInfo
{
    use HandlesMessageStore;
    use HandlesMetadata;
    use HandlesStateInfo;
    use HandlesAgentSteps;
    use HandlesUsage;

    private AgentStatus $status;
    private CachedContext $cache;

    public string $agentId;
    public ?string $parentAgentId;
    public ?DateTimeImmutable $currentStepStartedAt;

    public function __construct(
        ?AgentStatus        $status = null,
        ?AgentSteps         $steps = null,
        ?AgentStep          $currentStep = null,

        Metadata|array|null $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?string             $agentId = null,
        ?string             $parentAgentId = null,
        ?DateTimeImmutable  $currentStepStartedAt = null,
        ?CachedContext      $cache = null,
    ) {
        $this->agentId = $agentId ?? Uuid::uuid4();
        $this->parentAgentId = $parentAgentId;
        $this->currentStepStartedAt = $currentStepStartedAt;

        $this->status = $status ?? AgentStatus::InProgress;
        $this->steps = $steps ?? new AgentSteps();
        $this->currentStep = $currentStep ?? null;

        $this->stateInfo = $stateInfo ?? StateInfo::new();
        $this->metadata = match(true) {
            $variables === null => new Metadata(),
            $variables instanceof Metadata => $variables,
            is_array($variables) => new Metadata($variables),
            default => new Metadata(),
        };
        $this->cache = $cache ?? new CachedContext();
        $this->usage = $usage ?? new Usage();
        $this->store = $store ?? new MessageStore();
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty() : self {
        return new self();
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentStatus        $status = null,
        ?AgentSteps         $steps = null,
        ?AgentStep          $currentStep = null,

        ?Metadata           $variables = null,
        ?Usage              $usage = null,
        ?MessageStore       $store = null,
        ?StateInfo          $stateInfo = null,
        ?string             $agentId = null,
        ?string             $parentAgentId = null,
        ?DateTimeImmutable  $currentStepStartedAt = null,
        ?CachedContext      $cache = null,
    ): self {
        return new self(
            status: $status ?? $this->status,
            steps: $steps ?? $this->steps,
            currentStep: $currentStep ?? $this->currentStep,
            variables: $variables ?? $this->metadata,
            cache: $cache ?? $this->cache,
            usage: $usage ?? $this->usage,
            store: $store ?? $this->store,
            stateInfo: $stateInfo ?? $this->stateInfo->touch(),
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            currentStepStartedAt: $currentStepStartedAt ?? $this->currentStepStartedAt,
        );
    }

    public function withStatus(AgentStatus $status) : self {
        return $this->with(status: $status);
    }

    public function withCurrentStepStartedAt(?DateTimeImmutable $startedAt) : self {
        return $this->with(currentStepStartedAt: $startedAt);
    }

    public function markStepStarted() : self {
        return $this->with(currentStepStartedAt: new DateTimeImmutable());
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function status() : AgentStatus {
        return $this->status;
    }

    public function cache() : CachedContext {
        return $this->cache;
    }

    public function withCachedContext(CachedContext $cache) : self {
        return $this->with(cache: $cache);
    }

    // SERIALIZATION ////////////////////////////////////////

    public function toArray() : array {
        return [
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'currentStepStartedAt' => $this->currentStepStartedAt?->format(DATE_ATOM),
            'metadata' => $this->metadata->toArray(),
            'cachedContext' => $this->cacheToArray($this->cache),
            'usage' => $this->usage->toArray(),
            'messageStore' => $this->store->toArray(),
            'stateInfo' => $this->stateInfo->toArray(),
            'currentStep' => $this->currentStep?->toArray(),
            'status' => $this->status->value,
            'steps' => array_map(static fn(AgentStep $step) => $step->toArray(), $this->steps->all()),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            status: isset($data['status']) ? AgentStatus::from($data['status']) : AgentStatus::InProgress,
            steps: isset($data['steps']) ? AgentSteps::fromArray($data['steps']) : new AgentSteps(),
            currentStep: isset($data['currentStep']) ? AgentStep::fromArray($data['currentStep']) : null,

            variables: isset($data['metadata']) ? Metadata::fromArray($data['metadata']) : new Metadata(),
            cache: self::cacheFromArray($data),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : new Usage(),
            store: isset($data['messageStore']) ? MessageStore::fromArray($data['messageStore']) : new MessageStore(),
            stateInfo: isset($data['stateInfo']) ? StateInfo::fromArray($data['stateInfo']) : null,
            agentId: $data['agentId'] ?? null,
            parentAgentId: $data['parentAgentId'] ?? null,
            currentStepStartedAt: isset($data['currentStepStartedAt']) ? new DateTimeImmutable($data['currentStepStartedAt']) : null,
        );
    }

    // INTERNAL ////////////////////////////////////////////////

    private function cacheToArray(CachedContext $cache) : array {
        $responseFormat = $cache->responseFormat();
        $responseFormatData = $responseFormat->isEmpty()
            ? []
            : [
                'type' => $responseFormat->type(),
                'schema' => $responseFormat->schema(),
                'name' => $responseFormat->schemaName(),
                'strict' => $responseFormat->strict(),
            ];

        return [
            'messages' => $cache->messages()->toArray(),
            'tools' => $cache->tools(),
            'toolChoice' => $cache->toolChoice(),
            'responseFormat' => $responseFormatData,
        ];
    }

    private static function cacheFromArray(array $data) : CachedContext {
        $cacheData = match (true) {
            isset($data['cachedContext']) && is_array($data['cachedContext']) => $data['cachedContext'],
            isset($data['cache']) && is_array($data['cache']) => $data['cache'],
            default => [],
        };

        if ($cacheData === []) {
            return new CachedContext();
        }

        $messages = $cacheData['messages'] ?? [];
        $tools = $cacheData['tools'] ?? [];
        $toolChoice = $cacheData['toolChoice'] ?? $cacheData['tool_choice'] ?? [];
        $responseFormat = $cacheData['responseFormat'] ?? $cacheData['response_format'] ?? null;

        $normalizedResponseFormat = match (true) {
            is_array($responseFormat) && $responseFormat !== [] => $responseFormat,
            default => null,
        };

        return new CachedContext(
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $normalizedResponseFormat,
        );
    }
}
