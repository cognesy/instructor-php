<?php declare(strict_types=1);

use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;

it('returns a fresh instance from default() to avoid cross-call cache sharing', function () {
    $first = SchemaFactory::default();
    $second = SchemaFactory::default();

    expect($first)->not->toBe($second);
});

it('does not share internal schema cache between default() calls', function () {
    $first = SchemaFactory::default();
    $second = SchemaFactory::default();

    $first->schema(SimpleClass::class);

    $cacheReader = static function (SchemaFactory $factory): int {
        /** @var array<string, mixed> $cache */
        $cache = (fn() => $this->schemaCache)->call($factory);
        return count($cache);
    };

    expect($cacheReader($first))->toBeGreaterThan(0);
    expect($cacheReader($second))->toBe(0);
});
