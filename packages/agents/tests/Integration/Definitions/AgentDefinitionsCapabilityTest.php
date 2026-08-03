<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Definitions;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Definitions\UseAgentDefinitions;
use Cognesy\Agents\Capability\Definitions\WriteAgentTool;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Template\AgentDefinitionLoader;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\FileAgentDefinitionStore;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\FakeTool;
use InvalidArgumentException;

function definitionsCapabilityFixture(): string {
    $root = sys_get_temp_dir() . '/agent-definitions-capability-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    return $root;
}

function removeDefinitionsCapabilityFixture(string $root): void {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $path) {
        match ($path->isDir()) {
            true => rmdir($path->getPathname()),
            false => unlink($path->getPathname()),
        };
    }
    rmdir($root);
}

function definitionData(string $name = 'written-agent', string $prompt = 'Original prompt.'): array {
    return [
        'name' => $name,
        'description' => 'Agent written by the definitions tool',
        'systemPrompt' => $prompt,
        'capabilities' => [],
        'tools' => [],
    ];
}

function directoryBytes(string $root): array {
    $files = glob($root . '/*');
    if ($files === false) {
        return [];
    }
    $bytes = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            $bytes[basename($file)] = file_get_contents($file);
        }
    }
    ksort($bytes);
    return $bytes;
}

function recursiveDirectoryBytes(string $root): array {
    $bytes = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $bytes[$relative] = file_get_contents($file->getPathname());
        }
    }
    ksort($bytes);
    return $bytes;
}

it('uses the same capability and tool reference rules as DefinitionLoopFactory', function () {
    $capabilities = new AgentCapabilityRegistry();
    $tools = new ToolRegistry();
    $validator = new AgentDefinitionValidator($capabilities, $tools);
    $factory = new DefinitionLoopFactory($capabilities, $tools);

    $unknownCapability = new AgentDefinition(
        name: 'unknown-capability',
        description: 'Invalid reference',
        systemPrompt: 'Test.',
        capabilities: new NameList('missing-capability'),
        tools: new NameList(),
    );
    $unknownTool = new AgentDefinition(
        name: 'unknown-tool',
        description: 'Invalid reference',
        systemPrompt: 'Test.',
        tools: new NameList('missing-tool'),
    );
    $unknownDeniedTool = new AgentDefinition(
        name: 'unknown-denied-tool',
        description: 'Invalid reference',
        systemPrompt: 'Test.',
        toolsDeny: new NameList('missing-denied-tool'),
    );

    foreach ([$unknownCapability, $unknownTool, $unknownDeniedTool] as $definition) {
        expect($validator->validate($definition)->isValid())->toBeFalse();
        expect(fn () => $factory->instantiateAgentLoop($definition))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('exposes list and read tools but not write in read-only mode', function () {
    $registry = new AgentDefinitionRegistry();
    $registry->register(AgentDefinition::fromArray(definitionData()));
    $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
    $loop = AgentBuilder::base()
        ->withCapability(new UseAgentDefinitions($registry, $validator))
        ->build();

    expect($loop->tools()->names())->toBe(['list_agents', 'read_agent'])
        ->and($loop->tools()->get('list_agents')(...[])['agents'][0]['name'])->toBe('written-agent')
        ->and($loop->tools()->get('read_agent')(name: 'written-agent')['source'])->toContain('Original prompt.');
});

it('rejects invalid and traversal-like definitions without writing any bytes', function (string $name) {
    $root = definitionsCapabilityFixture();
    try {
        mkdir($root . '/writable');
        $registry = new AgentDefinitionRegistry();
        $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
        $tool = new WriteAgentTool($registry, $validator, new FileAgentDefinitionStore($root . '/writable'));
        $before = recursiveDirectoryBytes($root);
        $result = $tool(definition: definitionData($name));
        expect($result['success'])->toBeFalse()
            ->and(recursiveDirectoryBytes($root))->toBe($before)
            ->and($registry->count())->toBe(0);
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
})->with([
    'slash' => '../outside',
    'nested path' => 'nested/agent',
    'dot segments' => '..',
    'null byte' => "agent\0name",
    'uppercase' => 'BadAgent',
]);

it('rejects invalid fields before persistence', function () {
    $root = definitionsCapabilityFixture();
    try {
        $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
        $tool = new WriteAgentTool(new AgentDefinitionRegistry(), $validator, new FileAgentDefinitionStore($root));
        $data = definitionData();
        $data['description'] = '';
        $result = $tool(definition: $data);
        expect($result['success'])->toBeFalse()
            ->and($result['problems'][0]['field'])->toBe('description')
            ->and(directoryBytes($root))->toBe([]);
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
});

it('guards overwrite byte-for-byte then replaces and refreshes the registry explicitly', function () {
    $root = definitionsCapabilityFixture();
    try {
        mkdir($root . '/.claude/agents', 0777, true);
        $storeRoot = $root . '/.claude/agents';
        $registry = new AgentDefinitionRegistry();
        $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
        $tool = new WriteAgentTool($registry, $validator, new FileAgentDefinitionStore($storeRoot));

        $created = $tool(definition: definitionData());
        $original = file_get_contents($storeRoot . '/written-agent.md');
        $refused = $tool(definition: definitionData(prompt: 'Replacement prompt.'));
        expect($created['created'])->toBeTrue()
            ->and($refused['success'])->toBeFalse()
            ->and(file_get_contents($storeRoot . '/written-agent.md'))->toBe($original)
            ->and($registry->get('written-agent')->systemPrompt)->toBe('Original prompt.');

        $replaced = $tool(definition: definitionData(prompt: 'Replacement prompt.'), overwrite: true);
        expect($replaced['replaced'])->toBeTrue()
            ->and(file_get_contents($storeRoot . '/written-agent.md'))->not()->toBe($original)
            ->and($registry->get('written-agent')->systemPrompt)->toBe('Replacement prompt.');

        $fresh = new AgentDefinitionRegistry(new AgentDefinitionLoader());
        $fresh->autoDiscover($root);
        expect($fresh->get('written-agent')->canonicalArray())
            ->toBe(AgentDefinition::fromArray(definitionData(prompt: 'Replacement prompt.'))->canonicalArray());
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
});

it('executes write_agent through the real loop tool executor', function () {
    $root = definitionsCapabilityFixture();
    try {
        $registry = new AgentDefinitionRegistry();
        $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
        $driver = new FakeAgentDriver([
            ScenarioStep::toolCall(
                WriteAgentTool::TOOL_NAME,
                ['definition' => definitionData('executed-agent')],
                executeTools: true,
            ),
        ]);
        $loop = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->withCapability(new UseAgentDefinitions(
                $registry,
                $validator,
                new FileAgentDefinitionStore($root),
            ))
            ->build();

        $state = null;
        foreach ($loop->iterate(AgentState::empty()) as $iteration) {
            $state = $iteration;
            break;
        }
        $executions = $state?->lastStepToolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1)
            ->and($executions[0]->name())->toBe(WriteAgentTool::TOOL_NAME)
            ->and($executions[0]->hasError())->toBeFalse()
            ->and(is_file($root . '/executed-agent.md'))->toBeTrue()
            ->and($registry->has('executed-agent'))->toBeTrue();
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
});

it('reports required fields, skills, and negative budget values', function () {
    $root = definitionsCapabilityFixture();
    try {
        mkdir($root . '/known-skill');
        file_put_contents($root . '/known-skill/SKILL.md', "---\nname: known-skill\ndescription: Known\n---\nBody\n");
        $validator = new AgentDefinitionValidator(
            new AgentCapabilityRegistry(),
            new ToolRegistry(),
            SkillLibrary::inDirectory($root),
        );
        $definition = new AgentDefinition(
            name: 'valid-name',
            description: ' ',
            systemPrompt: "\n",
            skills: new NameList('missing-skill'),
            budget: new ExecutionBudget(maxSteps: -1, maxCost: -0.5),
        );
        $fields = array_map(
            static fn ($problem): string => $problem->field,
            $validator->validate($definition)->problems(),
        );
        expect($fields)->toBe([
            'description',
            'systemPrompt',
            'skills',
            'budget.maxSteps',
            'budget.maxCost',
        ]);
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
});

it('adds write only when a writable store is explicitly supplied', function () {
    $root = definitionsCapabilityFixture();
    try {
        $registry = new AgentDefinitionRegistry();
        $validator = new AgentDefinitionValidator(new AgentCapabilityRegistry(), new ToolRegistry());
        $loop = AgentBuilder::base()
            ->withCapability(new UseAgentDefinitions(
                $registry,
                $validator,
                new FileAgentDefinitionStore($root),
            ))
            ->build();
        expect($loop->tools()->names())->toBe(['list_agents', 'read_agent', 'write_agent'])
            ->and($loop->tools()->get('write_agent')->toToolSchema()->toArray()['function']['parameters']['properties'])
            ->not()->toHaveKey('path');
    } finally {
        removeDefinitionsCapabilityFixture($root);
    }
});
