<?php declare(strict_types=1);

use Cognesy\Agents\SelfKnowledge\PackageDocs;
use Cognesy\Agents\SelfKnowledge\SelfKnowledgeTopics;

it('resolves installed documentation to absolute existing package paths', function () {
    $docs = PackageDocs::installed();

    expect($docs->exists())->toBeTrue()
        ->and($docs->docsPath())->toStartWith(DIRECTORY_SEPARATOR)
        ->and($docs->readmePath())->toStartWith(DIRECTORY_SEPARATOR)
        ->and($docs->docsPath())->toBeDirectory()
        ->and($docs->readmePath())->toBeFile()
        ->and($docs->examplesPath())->toBeNull();
});

it('routes every curated document and leaves no mirror orphan', function () {
    $docs = PackageDocs::installed();
    $topics = SelfKnowledgeTopics::agents();
    $routed = $topics->files();
    sort($routed);

    $mirrored = array_map('basename', glob($docs->docsPath() . '/*.md') ?: []);
    sort($mirrored);

    foreach ($routed as $file) {
        expect($docs->docsPath() . '/' . $file)->toBeFile();
    }
    expect($routed)->toBe($mirrored)
        ->and($routed)->toContain('01-introduction.md');
});

it('exposes examples only when an explicit examples root exists', function () {
    $root = sys_get_temp_dir() . '/agents-examples-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);

    try {
        expect(PackageDocs::installed()->examplesPath())->toBeNull()
            ->and(PackageDocs::installed($root)->examplesPath())->toBe(realpath($root));
    } finally {
        rmdir($root);
    }
});
