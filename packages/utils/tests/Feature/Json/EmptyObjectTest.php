<?php declare(strict_types=1);

namespace Cognesy\Utils\Tests\Feature\Json;

use Cognesy\Utils\Json\EmptyObject;
use Cognesy\Utils\Json\Json;

it('serializes empty object as {}', function () {
    $empty = new EmptyObject();

    expect(json_encode($empty))->toBe('{}');
    expect(Json::encode($empty))->toBe('{}');
});
