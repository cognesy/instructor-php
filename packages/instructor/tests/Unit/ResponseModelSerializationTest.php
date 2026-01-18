<?php declare(strict_types=1);

use Cognesy\Instructor\Data\ResponseModel;

it('serializes toArray when instance is not an object', function () {
    $model = makeAnyResponseModel(\stdClass::class);
    assert($model instanceof ResponseModel);

    $updated = $model->withInstance('not-object');

    expect($updated->toArray())->toBeArray();
});
