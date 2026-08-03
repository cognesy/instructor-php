<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Coding\UseCodingTools;
use Cognesy\Agents\Capability\Core\UseContextConfig;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Core\UseToolFactory;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\AgentIdentity;
use Cognesy\Agents\Profile\Contracts\CanAcceptAgentProfile;
use Cognesy\Agents\Profile\PromptGuidelines;
use Cognesy\Agents\Profile\SystemPromptComposer;
use Cognesy\Agents\Profile\ToolProfile;
use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tests\Support\PromptSections;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

final readonly class DerivedPromptDescriptor extends ToolDescriptor implements CanContributeToPrompt
{
    /** @param list<string> $guidelines */
    public function __construct(
        string $name,
        private ?string $snippet,
        private array $guidelines = [],
    ) {
        parent::__construct($name, "Long description for {$name}. Additional text must not leak.");
    }

    public function promptSnippet(): ?string {
        return $this->snippet;
    }

    public function promptGuidelines(): array {
        return $this->guidelines;
    }
}

final readonly class DerivedPromptTool implements ToolInterface, CanAcceptAgentProfile
{
    public function __construct(
        private CanDescribeTool $toolDescriptor,
        public ?AgentProfile $boundProfile = null,
    ) {}

    public static function named(string $name, ?string $snippet = null, array $guidelines = []): self {
        return new self(new DerivedPromptDescriptor($name, $snippet ?? "Use {$name}", $guidelines));
    }

    public static function hidden(string $name): self {
        return new self(new DerivedPromptDescriptor($name, null));
    }

    public function use(mixed ...$args): Result {
        return Result::success('ok');
    }

    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray([
            'type' => 'function',
            'function' => [
                'name' => $this->toolDescriptor->name(),
                'description' => $this->toolDescriptor->description(),
                'parameters' => ['type' => 'object'],
            ],
        ]);
    }

    public function descriptor(): CanDescribeTool {
        return $this->toolDescriptor;
    }

    public function withAgentProfile(AgentProfile $profile): static {
        return new self($this->toolDescriptor, $profile);
    }
}

function derivedPrompt(array $tools): string {
    $loop = AgentBuilder::base()
        ->withCapability(new UseTools(...$tools))
        ->build();
    return (new SystemPromptComposer())->compose($loop->profile());
}

it('derives the parsed tool set from varying resolved tools', function () {
    $two = derivedPrompt([
        DerivedPromptTool::named('read'),
        DerivedPromptTool::named('bash'),
    ]);
    $three = derivedPrompt([
        DerivedPromptTool::named('read'),
        DerivedPromptTool::named('bash'),
        DerivedPromptTool::named('edit'),
    ]);

    expect(PromptSections::toolNames($two))->toBe(['read', 'bash'])
        ->and(PromptSections::toolNames($three))->toBe(['read', 'bash', 'edit']);
});

it('keeps a hidden tool callable while omitting it from the prompt profile', function () {
    $visible = DerivedPromptTool::named('visible');
    $hidden = DerivedPromptTool::hidden('internal');
    $loop = AgentBuilder::base()
        ->withCapability(new UseTools($visible, $hidden))
        ->build();
    $prompt = (new SystemPromptComposer())->compose($loop->profile());

    expect($loop->tools()->has('internal'))->toBeTrue()
        ->and($loop->profile()->tools->get('internal')->promptSnippet)->toBeNull()
        ->and(PromptSections::toolNames($prompt))->toBe(['visible']);
});

it('deduplicates contributed guidelines in first-occurrence order', function () {
    $loop = AgentBuilder::base()->withCapability(new UseTools(
        DerivedPromptTool::named('a', guidelines: [' First ', 'Shared']),
        DerivedPromptTool::named('b', guidelines: ['Shared ', 'Second']),
    ))->build();

    $guidelines = PromptGuidelines::collect($loop->profile()->tools, [' First']);
    expect(array_slice($guidelines, 0, 3))->toBe(['First', 'Shared', 'Second'])
        ->and(array_count_values($guidelines)['First'])->toBe(1)
        ->and(array_count_values($guidelines)['Shared'])->toBe(1);
});

it('selects exploration guidance from the actual tool matrix', function (array $names, ?string $present, array $absent) {
    $tools = array_map(
        static fn (string $name): DerivedPromptTool => DerivedPromptTool::named($name),
        $names,
    );
    $profile = AgentBuilder::base()->withCapability(new UseTools(...$tools))->build()->profile();
    $guidelines = PromptGuidelines::collect($profile->tools);

    if ($present !== null) {
        expect($guidelines)->toContain($present);
    }
    foreach ($absent as $guideline) {
        expect($guidelines)->not()->toContain($guideline);
    }
})->with([
    'bash only' => [
        ['bash'],
        'Use bash for file operations like ls, rg, find',
        ['Prefer the dedicated file tools over bash for exploration'],
    ],
    'bash and grep' => [
        ['bash', 'grep'],
        'Prefer the dedicated file tools over bash for exploration',
        ['Use bash for file operations like ls, rg, find'],
    ],
    'read only' => [
        ['read'],
        null,
        [
            'Use bash for file operations like ls, rg, find',
            'Prefer the dedicated file tools over bash for exploration',
        ],
    ],
]);

it('renders an explicit none entry for an empty tool set', function () {
    $prompt = derivedPrompt([]);
    expect(PromptSections::toolNames($prompt))->toBe([])
        ->and(preg_match('/Available tools:\s*\(none\)/', $prompt))->toBe(1);
});

it('bounds fallback descriptor summaries at a sentence or hard character limit', function () {
    $sentence = ToolProfile::fromDescriptor(new ToolDescriptor(
        name: 'sentence',
        description: 'First sentence. This content must not enter the prompt.',
        metadata: ['summary' => 'First sentence. This content must not enter the prompt.'],
    ));
    $bounded = ToolProfile::fromDescriptor(new ToolDescriptor(
        name: 'bounded',
        description: str_repeat('x', 300),
        metadata: ['summary' => str_repeat('x', 300)],
    ));
    $unicode = ToolProfile::fromDescriptor(new ToolDescriptor(
        name: 'unicode',
        description: str_repeat('ż', 300),
        metadata: ['summary' => str_repeat('ż', 300)],
    ));

    expect($sentence->promptSnippet)->toBe('First sentence.')
        ->and(mb_strlen($bounded->promptSnippet ?? ''))->toBe(160)
        ->and($bounded->promptSnippet)->toEndWith('...')
        ->and(mb_strlen($unicode->promptSnippet ?? ''))->toBe(160)
        ->and(json_encode($unicode->toArray(), JSON_THROW_ON_ERROR))->toBeString();
});

it('marks tools produced by deferred factories in the resolved profile', function () {
    $loop = AgentBuilder::base()
        ->withCapability(new UseTools(DerivedPromptTool::named('eager')))
        ->withCapability(new UseToolFactory(
            static fn (
                Tools $tools,
                CanUseTools $driver,
                CanHandleEvents $events,
            ): ToolInterface => DerivedPromptTool::named('deferred'),
        ))
        ->build();

    expect($loop->profile()->tools->get('eager')->deferred)->toBeFalse()
        ->and($loop->profile()->tools->get('deferred')->deferred)->toBeTrue()
        ->and($loop->describe()->toArray()['tools'][1]['deferred'])->toBeTrue();
});

it('preserves explicit identity and installed capability order in the built profile', function () {
    $loop = AgentBuilder::base()
        ->withIdentity(new AgentIdentity('reviewer', 'Reviews resolved agent behavior.'))
        ->withCapability(new UseTools(DerivedPromptTool::named('inspect')))
        ->withCapability(new UseSystemPrompt())
        ->build();

    expect($loop->profile()->name())->toBe('reviewer')
        ->and($loop->profile()->description())->toBe('Reviews resolved agent behavior.')
        ->and(array_column($loop->profile()->capabilities->toArray(), 'name'))
        ->toBe(['use_tools', 'use_system_prompt']);
});

it('replaces its owned block idempotently across consecutive executions', function () {
    $loop = AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('first', 'second')))
        ->withCapability(new UseContextConfig(systemPrompt: 'Configured persona'))
        ->withCapability(new UseTools(DerivedPromptTool::named('search')))
        ->withCapability(new UseSystemPrompt())
        ->withCapability(new UseGuards(maxSteps: 1, maxTokens: null, maxExecutionTime: null))
        ->build();

    $first = $loop->execute(AgentState::empty()->withUserMessage('one'));
    $second = $loop->execute($first->withUserMessage('two'));
    $prompt = $second->context()->systemPrompt();

    expect(substr_count($prompt, '<!-- cognesy-agent-profile:start -->'))->toBe(1)
        ->and(substr_count($prompt, '<!-- cognesy-agent-profile:end -->'))->toBe(1)
        ->and(PromptSections::toolNames($prompt))->toBe(['search']);
});

it('applies append and override semantics to an existing prompt', function (bool $override, bool $retainsBase) {
    $loop = AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('done')))
        ->withCapability(new UseTools(DerivedPromptTool::named('lookup')))
        ->withCapability(new UseSystemPrompt(overrideExisting: $override))
        ->withCapability(new UseGuards(maxSteps: 1, maxTokens: null, maxExecutionTime: null))
        ->build();
    $state = $loop->execute(AgentState::empty()
        ->withSystemPrompt('Existing persona')
        ->withUserMessage('go'));

    expect(str_contains($state->context()->systemPrompt(), 'Existing persona'))->toBe($retainsBase)
        ->and(PromptSections::toolNames($state->context()->systemPrompt()))->toBe(['lookup']);
})->with([
    'append' => [false, true],
    'override' => [true, false],
]);

it('sends context configuration and the derived block through the real inference request boundary', function () {
    $capturingInference = new class implements CanCreateInference {
        public ?InferenceRequest $captured = null;

        public function create(?InferenceRequest $request = null): PendingInference {
            assert($request instanceof InferenceRequest);
            $this->captured = $request;
            return new PendingInference(
                execution: InferenceExecution::fromRequest($request),
                driver: new Cognesy\Agents\Tests\Support\FakeInferenceDriver([
                    InferenceResponse::empty()->withContent('done'),
                ]),
                eventDispatcher: new EventDispatcher(),
            );
        }
    };
    $driver = new Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver(
        inference: $capturingInference,
    );
    $loop = AgentBuilder::base()
        ->withCapability(new UseDriver($driver))
        ->withCapability(new UseContextConfig(systemPrompt: 'Configured persona'))
        ->withCapability(new UseTools(DerivedPromptTool::named('lookup')))
        ->withCapability(new UseSystemPrompt())
        ->withCapability(new UseGuards(maxSteps: 1, maxTokens: null, maxExecutionTime: null))
        ->build();

    $loop->execute(AgentState::empty()->withUserMessage('go'));
    $systemMessage = $capturingInference->captured
        ?->cachedContext()
        ?->messages()
        ->first()
        ?->content()
        ->toString() ?? '';

    expect($capturingInference->captured)->toBeInstanceOf(InferenceRequest::class)
        ->and(str_starts_with($systemMessage, 'Configured persona'))->toBeTrue()
        ->and(PromptSections::toolNames($systemMessage))->toBe(['lookup']);
});

it('refreshes profiles and profile-aware consumers across loop copies', function () {
    $hook = new class implements HookInterface, CanAcceptAgentProfile {
        public function __construct(public ?AgentProfile $profile = null) {}
        public function handle(HookContext $context): HookContext { return $context; }
        public function withAgentProfile(AgentProfile $profile): static { return new self($profile); }
    };
    $driver = new class implements CanUseTools, CanAcceptAgentProfile {
        public function __construct(public ?AgentProfile $profile = null) {}
        public function useTools(AgentState $state): AgentState { return $state; }
        public function withAgentProfile(AgentProfile $profile): static { return new self($profile); }
    };
    $interceptor = (new HookStack(new Cognesy\Agents\Hook\Collections\RegisteredHooks()))
        ->with($hook, HookTriggers::beforeStep(), name: 'aware');
    $initial = AgentBuilder::base()
        ->withCapability(new UseDriver($driver))
        ->withCapability(new UseTools(DerivedPromptTool::named('one')))
        ->build()
        ->withInterceptor($interceptor);
    $changed = $initial
        ->withTools(new Tools(DerivedPromptTool::named('one'), DerivedPromptTool::named('two')))
        ->withDriver($driver);

    $boundHook = $changed->interceptor() instanceof HookStack
        ? $changed->interceptor()->hooks()[0]->hook()
        : null;
    $boundTool = $changed->tools()->get('one');

    expect($initial->profile()->tools->names())->toBe(['one'])
        ->and($changed->profile()->tools->names())->toBe(['one', 'two'])
        ->and($changed->driver()->profile?->tools->names())->toBe(['one', 'two'])
        ->and($boundHook->profile?->tools->names())->toBe(['one', 'two'])
        ->and($boundTool->boundProfile?->tools->names())->toBe(['one', 'two']);
});

it('reproduces the structured coding prompt snapshot from resolved coding tools', function () {
    $loop = AgentBuilder::base()
        ->withCapability(new UseCodingTools('/workspace'))
        ->build();
    $prompt = (new SystemPromptComposer())->compose($loop->profile());
    $fixture = json_decode(
        file_get_contents(__DIR__ . '/../../Fixtures/Profile/coding-prompt-2.5.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $actualGuidelines = PromptSections::guidelines($prompt);
    $expectedGuidelines = $fixture['guidelines'];
    sort($actualGuidelines);
    sort($expectedGuidelines);

    expect(PromptSections::toolNames($prompt))->toBe($fixture['tools'])
        ->and($actualGuidelines)->toBe($expectedGuidelines);
});
