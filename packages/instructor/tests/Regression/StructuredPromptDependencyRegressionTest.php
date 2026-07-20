<?php declare(strict_types=1);

it('declares Twig as a runtime dependency for structured prompt templates', function () {
    $rootComposer = json_decode((string) file_get_contents(__DIR__ . '/../../../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $packageComposer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($rootComposer['require'])->toHaveKey('twig/twig')
        ->and($packageComposer['require'])->toHaveKey('twig/twig')
        ->and($rootComposer['suggest'] ?? [])->not->toHaveKey('twig/twig')
        ->and($packageComposer['suggest'] ?? [])->not->toHaveKey('twig/twig');
})->group('structured-output-contract-regression');

it('keeps Instructor independent from the Dynamic package and namespace', function () {
    $packageRoot = dirname(__DIR__, 2);
    $packageComposer = json_decode((string) file_get_contents($packageRoot . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $sourceFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageRoot . '/src', FilesystemIterator::SKIP_DOTS),
    );
    $source = '';
    foreach ($sourceFiles as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        $source .= (string) file_get_contents($file->getPathname());
    }

    expect($packageComposer['require'])->not->toHaveKey('cognesy/instructor-dynamic')
        ->and($source)->not->toContain('Cognesy\\Dynamic');
})->group('structured-output-contract-regression');
