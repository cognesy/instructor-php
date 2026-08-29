<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Collections\Tools;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Runtime\TellAgentFactory;

function tellCodingTools(TellAgentFactory $factory, string $project): Tools {
    return $factory->build(new TellOptions(prompt: 'Inspect coding tools.', directory: $project))->tools();
}

it('exposes canonical coding tools with explicit legacy aliases', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $tools = tellCodingTools($factory, $project);

    expect($tools->names())
        ->toContain('read_file', 'write_file', 'apply_patch', 'shell', 'read', 'write', 'edit', 'bash')
        ->and($tools->get('read')->descriptor()->metadata()['aliasOf'])->toBe('read_file')
        ->and($tools->get('edit')->descriptor()->metadata()['aliasOf'])->toBe('apply_patch')
        ->and($tools->get('bash')->descriptor()->metadata()['aliasOf'])->toBe('shell')
        ->and($tools->get('apply_patch')->descriptor()->metadata()['effect'])->toBe('write')
        ->and($tools->get('shell')->descriptor()->metadata()['bounds']['network'])->toBeFalse();
});

it('runs canonical and legacy read write and shell names through equivalent operations', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/notes.txt', "Zażółć\n");
    $tools = tellCodingTools($factory, $project);

    $canonicalRead = $tools->get('read_file')->use(path: $project . '/notes.txt')->unwrap();
    $legacyRead = $tools->get('read')->use(path: $project . '/notes.txt')->unwrap();
    $canonicalWrite = $tools->get('write_file')->use(path: $project . '/canonical.txt', content: "one\n")->unwrap();
    $legacyWrite = $tools->get('write')->use(path: $project . '/legacy.txt', content: "one\n")->unwrap();
    $canonicalShell = $tools->get('shell')->use(command: 'printf ready')->unwrap();
    $legacyShell = $tools->get('bash')->use(command: 'printf ready')->unwrap();

    expect($canonicalRead['success'])->toBeTrue()
        ->and($canonicalRead['data'])->toBe($legacyRead['data'])
        ->and($canonicalWrite['success'])->toBeTrue()
        ->and($legacyWrite['success'])->toBeTrue()
        ->and($canonicalWrite['data']['text'])->toContain('Successfully wrote 4 bytes')
        ->and($legacyWrite['data']['text'])->toContain('Successfully wrote 4 bytes')
        ->and($canonicalShell['data'])->toBe($legacyShell['data'])
        ->and($canonicalShell['truncated'])->toBeFalse();
});

it('applies unicode multi-hunk patches only after all hunks validate', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/notes.txt', "alpha\nZażółć\nomega\n");
    $tools = tellCodingTools($factory, $project);

    $patch = "--- a/notes.txt\n+++ b/notes.txt\n@@ -1,3 +1,3 @@\n-alpha\n+beta\n Zażółć\n-omega\n+done\n";
    $success = $tools->get('apply_patch')->use(patch: $patch)->unwrap();
    $beforeFailure = file_get_contents($project . '/notes.txt');
    $failed = $tools->get('apply_patch')->use(patch: "--- a/notes.txt\n+++ b/notes.txt\n@@ -1 +1 @@\n-missing\n+never\n")->unwrap();

    expect($success['success'])->toBeTrue()
        ->and($success['partial'])->toBeFalse()
        ->and(file_get_contents($project . '/notes.txt'))->toBe("beta\nZażółć\ndone\n")
        ->and($failed['success'])->toBeFalse()
        ->and($failed['error']['code'])->toBe('hunk_failed')
        ->and(file_get_contents($project . '/notes.txt'))->toBe($beforeFailure);
});

it('keeps the legacy edit schema on the same bounded patch operation', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/notes.txt', "draft\n");
    $tools = tellCodingTools($factory, $project);

    $result = $tools->get('edit')->use(
        path: $project . '/notes.txt',
        old_string: 'draft',
        new_string: 'verified',
    )->unwrap();

    expect($result['success'])->toBeTrue()
        ->and($result['operation'])->toBe('apply_patch')
        ->and($result['partial'])->toBeFalse()
        ->and(file_get_contents($project . '/notes.txt'))->toBe("verified\n");
});

it('denies traversal and symlink patch targets and rejects malformed or oversized patches', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $outside = tellLastTemporaryRoot() . '/outside.txt';
    file_put_contents($outside, "outside\n");
    symlink($outside, $project . '/linked.txt');
    $tools = tellCodingTools($factory, $project);

    $traversal = $tools->get('apply_patch')->use(patch: "--- a/../outside.txt\n+++ b/../outside.txt\n@@ -1 +1 @@\n-outside\n+changed\n")->unwrap();
    $symlink = $tools->get('apply_patch')->use(patch: "--- a/linked.txt\n+++ b/linked.txt\n@@ -1 +1 @@\n-outside\n+changed\n")->unwrap();
    $malformed = $tools->get('apply_patch')->use(patch: 'not a patch')->unwrap();
    $oversized = $tools->get('apply_patch')->use(patch: str_repeat('x', 262_145))->unwrap();

    expect($traversal['error']['code'])->toBe('path_denied')
        ->and($symlink['error']['code'])->toBe('path_denied')
        ->and($malformed['error']['code'])->toBe('malformed_patch')
        ->and($oversized['error']['code'])->toBe('invalid_patch_size')
        ->and(file_get_contents($outside))->toBe("outside\n");
});
