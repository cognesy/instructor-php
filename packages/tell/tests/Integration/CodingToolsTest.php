<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Collections\Tools;
use Cognesy\Tell\Adapter\Console\Symfony\TellOptions;
use Cognesy\Tell\Core\Agent\TellAgentFactory;

function tellCodingTools(TellAgentFactory $factory, string $project): Tools {
    return $factory->build(
        (new TellOptions(prompt: 'Inspect coding tools.', directory: $project))->request(),
    )->tools();
}

it('exposes one canonical name for each coding operation', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $tools = tellCodingTools($factory, $project);

    expect($tools->names())->toContain('read_file', 'write_file', 'apply_patch', 'shell')
        ->not->toContain('read', 'write', 'edit', 'bash')
        ->and($tools->get('apply_patch')->descriptor()->metadata()['effect'])->toBe('write')
        ->and($tools->get('shell')->descriptor()->metadata()['bounds']['network'])->toBeFalse();
});

it('runs read write and shell operations through their canonical names', function (): void {
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/notes.txt', "Zażółć\n");
    $tools = tellCodingTools($factory, $project);

    $read = $tools->get('read_file')->use(path: $project . '/notes.txt')->unwrap();
    $write = $tools->get('write_file')->use(path: $project . '/written.txt', content: "one\n")->unwrap();
    $shell = $tools->get('shell')->use(command: 'printf ready')->unwrap();

    expect($read['success'])->toBeTrue()
        ->and($read['data']['text'])->toContain('Zażółć')
        ->and($write['success'])->toBeTrue()
        ->and($write['data']['text'])->toContain('Successfully wrote 4 bytes')
        ->and($shell['data']['text'])->toBe('ready')
        ->and($shell['truncated'])->toBeFalse();
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
