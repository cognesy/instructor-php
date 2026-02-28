<?php declare(strict_types=1);

use Cognesy\Utils\Json\Json;

it('preserves valid falsy json values instead of returning default', function () {
    expect(Json::decode('0', 'DEF'))->toBe(0);
    expect(Json::decode('false', 'DEF'))->toBeFalse();
    expect(Json::decode('[]', ['default' => true]))->toBe([]);
    expect(Json::decode('""', 'DEF'))->toBe('');
    expect(Json::decode('null', 'DEF'))->toBeNull();
});

it('preserves valid falsy json values without default', function () {
    expect(Json::decode('0'))->toBe(0);
    expect(Json::decode('false'))->toBeFalse();
    expect(Json::decode('[]'))->toBe([]);
    expect(Json::decode('""'))->toBe('');
    expect(Json::decode('null'))->toBeNull();
});
