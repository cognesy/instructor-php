<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Value\DecodedObject;

it('provides key presence and value accessors', function () {
    $decoded = new DecodedObject([
        'event' => 'message',
        'count' => 3,
        'nullable' => null,
    ]);

    expect($decoded->has('event'))->toBeTrue()
        ->and($decoded->has('missing'))->toBeFalse()
        ->and($decoded->get('event'))->toBe('message')
        ->and($decoded->get('missing', 'fallback'))->toBe('fallback')
        ->and($decoded->get('nullable', 'fallback'))->toBeNull();
});

it('provides typed string accessors', function () {
    $decoded = new DecodedObject([
        'session_id' => 'sess_1',
        'empty' => '',
        'array' => ['k' => 'v'],
    ]);

    expect($decoded->getString('session_id'))->toBe('sess_1')
        ->and($decoded->getString('array', 'fallback'))->toBe('fallback')
        ->and($decoded->getNonEmptyString('session_id'))->toBe('sess_1')
        ->and($decoded->getNonEmptyString('empty'))->toBeNull()
        ->and($decoded->getNonEmptyString('missing'))->toBeNull();
});
