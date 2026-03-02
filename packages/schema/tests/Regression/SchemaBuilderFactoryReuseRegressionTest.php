<?php declare(strict_types=1);

it('does not call SchemaFactory::default repeatedly inside fluent methods', function () {
    $source = file_get_contents(__DIR__ . '/../../src/SchemaBuilder.php');
    expect($source)->not->toBeFalse();

    $defaultFactoryCalls = substr_count((string) $source, 'SchemaFactory::default()');

    expect($defaultFactoryCalls)->toBeLessThanOrEqual(1);
});
