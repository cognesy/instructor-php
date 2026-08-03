<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Template;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\AgentDefinitionLoader;
use Cognesy\Agents\Template\AgentDefinitionSerializer;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\FileAgentDefinitionStore;
use Cognesy\Agents\Template\Parsers\JsonDefinitionParser;
use Cognesy\Agents\Template\Parsers\MarkdownDefinitionParser;
use Cognesy\Agents\Template\Parsers\YamlDefinitionParser;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;

function definitionRoundTripFixture(): string {
    $root = sys_get_temp_dir() . '/agent-definition-roundtrip-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    return $root;
}

function removeDefinitionRoundTripFixture(string $root): void {
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

function roundTripDefinitions(): array {
    return [
        'minimal and normalized empty optionals' => new AgentDefinition(
            name: 'minimal-agent',
            label: 'minimal-agent',
            description: 'Minimal definition',
            systemPrompt: 'Be concise.',
            tools: new NameList(),
            skills: new NameList(),
            budget: ExecutionBudget::unlimited(),
            metadata: Metadata::empty(),
        ),
        'every field with special characters' => new AgentDefinition(
            name: 'full-agent',
            label: 'Full: * Agent #1',
            description: 'Handles unicode: Zażółć gęślą jaźń # safely',
            systemPrompt: "First line: keep # literal.\n---\n* leading marker and unicode ✓",
            llmConfig: new LLMConfig(model: 'fixture:model', maxTokens: 321),
            capabilities: new NameList('driver.fixture', 'self-knowledge'),
            tools: new NameList('read_file', 'search'),
            toolsDeny: new NameList('bash'),
            skills: new NameList('review', 'explain'),
            budget: new ExecutionBudget(
                maxSteps: 12,
                maxTokens: 3456,
                maxSeconds: 7.5,
                maxCost: 1.25,
                deadline: new DateTimeImmutable('2030-01-02T03:04:05+00:00'),
            ),
            metadata: Metadata::fromArray([
                'colon:key' => 'value # literal',
                'nested' => ['*leading', '日本語'],
            ]),
        ),
        'string LLM preset and absent collections' => new AgentDefinition(
            name: 'preset-agent',
            description: 'Uses a preset',
            systemPrompt: 'Follow the configured preset.',
            llmConfig: 'openai/fixture',
        ),
    ];
}

it('round-trips the canonical definition through Markdown YAML and JSON', function () {
    $serializer = new AgentDefinitionSerializer();
    $formats = [
        'markdown' => [$serializer->toMarkdown(...), new MarkdownDefinitionParser()],
        'yaml' => [$serializer->toYaml(...), new YamlDefinitionParser()],
        'json' => [$serializer->toJson(...), new JsonDefinitionParser()],
    ];

    foreach (roundTripDefinitions() as $definition) {
        foreach ($formats as [$serialize, $parser]) {
            $parsed = $parser->parse($serialize($definition));
            expect($parsed->canonicalArray())->toBe($definition->canonicalArray());
        }
    }
});

it('normalizes fallback labels without losing a distinct label', function () {
    $serializer = new AgentDefinitionSerializer();
    $same = roundTripDefinitions()['minimal and normalized empty optionals'];
    $distinct = roundTripDefinitions()['every field with special characters'];

    $sameSource = $serializer->toMarkdown($same);
    $distinctSource = $serializer->toMarkdown($distinct);
    expect($same->canonicalArray()['label'])->toBeNull()
        ->and($sameSource)->not()->toContain("label:")
        ->and($distinct->canonicalArray()['label'])->toBe('Full: * Agent #1')
        ->and($distinctSource)->toContain("label:");
});

it('persists a real Markdown file that reloads through AgentDefinitionLoader', function () {
    $root = definitionRoundTripFixture();
    $definition = roundTripDefinitions()['every field with special characters'];

    try {
        $stored = (new FileAgentDefinitionStore($root))->save($definition);
        $loaded = (new AgentDefinitionLoader())->loadFile($stored->path);
        expect($stored->replaced)->toBeFalse()
            ->and(realpath($stored->path))->toStartWith(realpath($root))
            ->and($loaded->canonicalArray())->toBe($definition->canonicalArray());
    } finally {
        removeDefinitionRoundTripFixture($root);
    }
});
