<?php declare(strict_types=1);

use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Config\ExampleGrouping;
use Cognesy\InstructorHub\Config\ExampleSource;
use Cognesy\InstructorHub\Config\ExampleSources;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExampleInfo;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Symfony\Component\Console\Tester\CommandTester;

it('parses tags from example front matter', function () {
    withTempDirectory(function (string $tempDir) {
        $path = createExample(
            $tempDir,
            'A01_Test/TaggedExample',
            <<<'PHP'
---
title: 'Tagged Example'
docname: 'tagged_example'
id: 'aaaa'
tags:
  - agents
  - streaming
---
# Tagged Example
PHP,
        );

        $info = ExampleInfo::fromFile($path, 'TaggedExample');

        expect($info->tags)->toBe(['agents', 'streaming']);
    });
});

it('filters examples by all requested tags case-insensitively', function () {
    withTempDirectory(function (string $tempDir) {
        createExample(
            $tempDir,
            'A01_Test/TaggedExample',
            exampleFrontMatter('Tagged Example', 'tagged_example', 'aaaa', ['agents', 'streaming']),
        );
        createExample(
            $tempDir,
            'A01_Test/AgentsOnly',
            exampleFrontMatter('Agents Only', 'agents_only', 'aaab', ['agents']),
        );
        createExample(
            $tempDir,
            'A01_Test/StreamingOnly',
            exampleFrontMatter('Streaming Only', 'streaming_only', 'aaac', ['streaming']),
        );

        $examples = repositoryFor($tempDir)->getExamplesMatchingTags(['AGENTS', 'streaming']);

        expect(array_map(fn($example) => $example->name, $examples))->toBe(['TaggedExample']);
    });
});

it('lists examples using combined tag filters', function () {
    withTempDirectory(function (string $tempDir) {
        createExample(
            $tempDir,
            'A01_Test/TaggedExample',
            exampleFrontMatter('Tagged Example', 'tagged_example', 'aaaa', ['agents', 'streaming']),
        );
        createExample(
            $tempDir,
            'A01_Test/AgentsOnly',
            exampleFrontMatter('Agents Only', 'agents_only', 'aaab', ['agents']),
        );

        $tester = new CommandTester(new ListAllExamples(repositoryFor($tempDir)));
        ob_start();
        $tester->execute([
            '--tag' => ['agents'],
            '--tags' => '[streaming]',
        ]);
        $output = Cli::removeColors(ob_get_clean() ?: '');

        expect($output)->toContain('Listing examples tagged with: agents, streaming');
        expect($output)->toContain('TaggedExample');
        expect($output)->not->toContain('AgentsOnly');
    });
});

function repositoryFor(string $baseDir): ExampleRepository
{
    return new ExampleRepository(
        ExampleSources::fromArray([ExampleSource::fromPath('test', $baseDir)]),
        ExampleGrouping::empty(),
    );
}

function createExample(string $baseDir, string $relativePath, string $contents): string
{
    $directory = $baseDir . '/' . $relativePath;
    mkdir($directory, 0777, true);

    $path = $directory . '/run.php';
    file_put_contents($path, $contents);

    return $path;
}

/**
 * @param string[] $tags
 */
function exampleFrontMatter(string $title, string $docName, string $id, array $tags): string
{
    $tagLines = implode("\n", array_map(
        fn(string $tag): string => "  - {$tag}",
        $tags,
    ));

    return <<<PHP
---
title: '{$title}'
docname: '{$docName}'
id: '{$id}'
tags:
{$tagLines}
---
# {$title}
PHP;
}

function deleteDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = scandir($directory) ?: [];
    foreach (array_diff($items, ['.', '..']) as $item) {
        $path = $directory . '/' . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
            continue;
        }
        unlink($path);
    }

    rmdir($directory);
}

function withTempDirectory(callable $callback): void
{
    $directory = sys_get_temp_dir() . '/hub-tags-' . bin2hex(random_bytes(8));
    mkdir($directory, 0777, true);

    try {
        $callback($directory);
    } finally {
        deleteDirectory($directory);
    }
}
