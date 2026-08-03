<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Describe\DescribeSelfTool;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Profile\AgentIdentity;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\Contracts\CanAcceptAgentProfile;
use Cognesy\Agents\Profile\SystemPromptComposer;
use Cognesy\Agents\Tests\Support\PromptSections;
use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;

final readonly class DescriptionToolDescriptor extends ToolDescriptor implements CanContributeToPrompt
{
    public function __construct(string $name, private ?string $snippet) {
        parent::__construct($name, "Description for {$name}");
    }

    public function promptSnippet(): ?string {
        return $this->snippet;
    }

    public function promptGuidelines(): array {
        return [];
    }
}

final readonly class DescriptionTool implements ToolInterface, CanAcceptAgentProfile
{
    public function __construct(
        private CanDescribeTool $toolDescriptor,
        public ?AgentProfile $boundProfile = null,
    ) {}

    public static function visible(string $name): self {
        return new self(new DescriptionToolDescriptor($name, "Use {$name}"));
    }

    public static function hidden(string $name): self {
        return new self(new DescriptionToolDescriptor($name, null));
    }

    public function use(mixed ...$args): Result {
        return Result::success('ok');
    }

    public function toToolSchema(): ToolDefinition {
        return new ToolDefinition(
            name: $this->toolDescriptor->name(),
            description: $this->toolDescriptor->description(),
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    public function descriptor(): CanDescribeTool {
        return $this->toolDescriptor;
    }

    public function withAgentProfile(AgentProfile $profile): static {
        return new self($this->toolDescriptor, $profile);
    }
}

function descriptionLoop(): Cognesy\Agents\AgentLoop {
    return AgentBuilder::base()
        ->withIdentity(new AgentIdentity('described', 'A deterministic described agent'))
        ->withCapability(new UseTools(
            DescriptionTool::visible('alpha'),
            DescriptionTool::hidden('internal'),
            DescriptionTool::visible('omega'),
        ))
        ->withCapability(new UseGuards(maxSteps: 3, maxTokens: null, maxExecutionTime: null))
        ->withCapability(new UseSystemPrompt())
        ->build();
}

it('describes the resolved tools and installed capabilities in runtime order', function (): void {
    $loop = descriptionLoop();
    $description = $loop->describe()->toArray();

    expect(array_column($description['tools'], 'name'))->toBe($loop->tools()->names())
        ->and(array_column($description['capabilities'], 'name'))
        ->toBe(array_map(static fn ($capability): string => $capability->name, $loop->profile()->capabilities->all()))
        ->and($description['name'])->toBe('described')
        ->and($description['description'])->toBe('A deterministic described agent');
});

it('refreshes descriptions across all loop copy boundaries', function (): void {
    $original = descriptionLoop();
    $toolCopy = $original->withTools(new Tools($original->tools()->get('alpha')));
    $driverCopy = $original->withDriver(FakeAgentDriver::fromResponses('done'));
    $interceptorCopy = $original->withInterceptor(new PassThroughInterceptor());

    expect(array_column($toolCopy->describe()->toArray()['tools'], 'name'))->toBe(['alpha'])
        ->and($driverCopy->describe()->toArray()['driver'])->toBe(FakeAgentDriver::class)
        ->and($interceptorCopy->describe()->toArray()['hooks'])->toBe([])
        ->and($original->describe()->toArray()['hooks'])->not->toBe([]);
});

it('uses the same visibility rule and resolved names as the composed prompt', function (): void {
    $loop = descriptionLoop();
    $tools = $loop->describe()->toArray()['tools'];
    $visible = array_values(array_map(
        static fn (array $tool): string => $tool['name'],
        array_filter($tools, static fn (array $tool): bool => $tool['visibleInPrompt']),
    ));
    $prompt = (new SystemPromptComposer())->compose($loop->profile());

    foreach ($tools as $tool) {
        expect($tool['visibleInPrompt'])->toBe($tool['promptSnippet'] !== null);
    }
    expect(PromptSections::toolNames($prompt))->toBe($visible);
});

it('is deterministic and keeps text and markdown complete', function (): void {
    $first = descriptionLoop()->describe();
    $second = descriptionLoop()->describe();

    expect($first->toArray())->toBe($second->toArray());
    foreach ($first->toArray()['tools'] as $tool) {
        expect($first->toText())->toContain($tool['name'])
            ->and($first->toMarkdown())->toContain($tool['name']);
    }
    foreach ($first->toArray()['capabilities'] as $capability) {
        expect($first->toText())->toContain($capability['name'])
            ->and($first->toMarkdown())->toContain($capability['name']);
    }
    expect(array_column($first->toArray()['hooks'], 'trigger'))->toBe(['before_step', 'before_step'])
        ->and(array_column($first->toArray()['hooks'], 'priority'))->toBe([200, -1000]);
});

it('exposes only the credential-safe llm projection', function (): void {
    $secrets = ['api-key-secret', 'url-user-secret', 'query-secret', 'metadata-secret', 'option-secret'];
    $config = new LLMConfig(
        apiUrl: 'https://url-user-secret@example.test',
        apiKey: $secrets[0],
        queryParams: ['token' => $secrets[2]],
        metadata: ['header' => $secrets[3]],
        model: 'safe-model',
        maxTokens: 1234,
        driver: 'openai',
        options: ['opaque' => $secrets[4]],
    );
    $llm = LLMProvider::fromLLMConfig($config);
    $loop = AgentBuilder::base()->withCapability(new UseDriver(new ToolCallingDriver(
        inference: InferenceRuntime::fromProvider($llm),
        llm: $llm,
    )))->build();
    $encoded = json_encode($loop->describe()->toArray(), JSON_THROW_ON_ERROR);

    foreach ($secrets as $secret) {
        expect($encoded)->not->toContain($secret);
    }
    expect($loop->describe()->toArray()['llm'])->toMatchArray([
        'driver' => 'openai',
        'model' => 'safe-model',
        'maxTokens' => 1234,
    ]);
});

it('returns the same description through the profile-bound describe self tool', function (): void {
    $loop = AgentBuilder::base()
        ->withCapability(new UseSelfDescription())
        ->build();
    $execution = $loop->toolExecutor()->executeTools(
        new ToolCalls(new ToolCall(DescribeSelfTool::TOOL_NAME, ['section' => 'all'])),
        AgentState::empty(),
    )->first();

    expect($execution?->hasError())->toBeFalse()
        ->and($execution?->value())->toBe($loop->describe()->toArray())
        ->and(array_column($loop->describe()->toArray()['tools'], 'name'))
        ->toContain(DescribeSelfTool::TOOL_NAME);

    $toolsOnly = $loop->tools()->get(DescribeSelfTool::TOOL_NAME)->use(section: 'tools')->unwrap();
    expect(array_keys($toolsOnly))->toBe(['tools'])
        ->and($toolsOnly['tools'])->toBe($loop->describe()->toArray()['tools']);
});
