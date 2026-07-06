<?php declare(strict_types=1);

it('declares Twig as a runtime dependency for structured prompt templates', function () {
    $rootComposer = json_decode((string) file_get_contents(__DIR__ . '/../../../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $packageComposer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($rootComposer['require'])->toHaveKey('twig/twig')
        ->and($packageComposer['require'])->toHaveKey('twig/twig');
});
