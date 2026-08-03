<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\SelfKnowledge\UseSelfKnowledge;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Profile\SystemPromptComposer;
use Cognesy\Agents\SelfKnowledge\PackageDocs;
use Cognesy\Agents\SelfKnowledge\SelfKnowledgeTopics;
use Cognesy\Agents\Tool\Tools\FakeTool;

function selfKnowledgePrompt(AgentBuilder $builder): array {
    $loop = $builder->build();
    return [$loop, (new SystemPromptComposer())->compose($loop->profile())];
}

it('is opt-in and contributes nothing without the capability', function () {
    [$loop, $prompt] = selfKnowledgePrompt(
        AgentBuilder::base()->withCapability(new UseTools(
            FakeTool::returning('read', 'Read files', 'ok'),
        )),
    );

    expect($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeFalse()
        ->and($loop->profile()->metadata->hasKey('prompt_sections'))->toBeFalse()
        ->and($prompt)->not()->toContain('Instructor Agents documentation');
});

it('requires a readable tool by default and adds no tool of its own', function () {
    [$loop, $prompt] = selfKnowledgePrompt(
        AgentBuilder::base()
            ->withCapability(new UseTools(FakeTool::returning('search', 'Search', 'ok')))
            ->withCapability(new UseSelfKnowledge()),
    );

    expect($loop->tools()->names())->toBe(['search'])
        ->and($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeFalse()
        ->and($prompt)->not()->toContain('Instructor Agents documentation');
});

it('does not mistake introspection read tags for file-reading capability', function () {
    [$loop, $prompt] = selfKnowledgePrompt(
        AgentBuilder::base()
            ->withCapability(new UseSelfDescription())
            ->withCapability(new UseSelfKnowledge()),
    );

    expect($loop->tools()->names())->toBe(['describe_self'])
        ->and($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeFalse()
        ->and($prompt)->not()->toContain('Instructor Agents documentation');
});

it('accepts an explicitly tagged file-reading tool', function () {
    $fileReader = new FakeTool(
        name: 'open_document',
        description: 'Open documentation files',
        handler: static fn (): string => 'ok',
        metadata: ['tags' => ['file', 'read']],
    );
    [$loop] = selfKnowledgePrompt(
        AgentBuilder::base()
            ->withCapability(new UseTools($fileReader))
            ->withCapability(new UseSelfKnowledge()),
    );

    expect($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeTrue();
});

it('exposes exact installed paths and complete routing with a readable tool', function () {
    $docs = PackageDocs::installed();
    [$loop, $prompt] = selfKnowledgePrompt(
        AgentBuilder::base()
            ->withCapability(new UseTools(FakeTool::returning('read', 'Read files', 'ok')))
            ->withCapability(new UseSelfKnowledge(docs: $docs)),
    );
    $knowledge = $loop->profile()->metadata->get('selfKnowledge');

    expect($knowledge)->toBeArray()
        ->and($knowledge['docsPath'])->toBe($docs->docsPath())
        ->and($knowledge['readmePath'])->toBe($docs->readmePath())
        ->and($knowledge['examplesPath'])->toBeNull()
        ->and($knowledge['topics'])->toBe(SelfKnowledgeTopics::agents()->toArray())
        ->and($prompt)->toContain('- Additional docs: ' . $docs->docsPath());
});

it('removes self-knowledge when a loop copy loses its readable tool', function () {
    $loop = AgentBuilder::base()
        ->withCapability(new UseTools(FakeTool::returning('read', 'Read files', 'ok')))
        ->withCapability(new UseSelfKnowledge())
        ->build();
    $withoutRead = $loop->withTools(new Tools(FakeTool::returning('search', 'Search', 'ok')));

    expect($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeTrue()
        ->and($withoutRead->profile()->metadata->hasKey('selfKnowledge'))->toBeFalse()
        ->and((new SystemPromptComposer())->compose($withoutRead->profile()))
        ->not()->toContain('Instructor Agents documentation');
});

it('degrades without throwing when installed resources are absent', function () {
    $root = sys_get_temp_dir() . '/agents-docs-missing-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    file_put_contents($root . '/README.md', 'Package');

    try {
        [$loop, $prompt] = selfKnowledgePrompt(
            AgentBuilder::base()
                ->withCapability(new UseTools(FakeTool::returning('read', 'Read files', 'ok')))
                ->withCapability(new UseSelfKnowledge(docs: PackageDocs::fromPackageRoot($root))),
        );

        expect($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeFalse()
            ->and($prompt)->not()->toContain('Instructor Agents documentation');
    } finally {
        unlink($root . '/README.md');
        rmdir($root);
    }
});

it('allows an explicit caller to waive the read-tool gate', function () {
    [$loop] = selfKnowledgePrompt(
        AgentBuilder::base()->withCapability(new UseSelfKnowledge(requireReadTool: false)),
    );

    expect($loop->tools()->isEmpty())->toBeTrue()
        ->and($loop->profile()->metadata->hasKey('selfKnowledge'))->toBeTrue();
});
