<?php declare(strict_types=1);

use Cognesy\Instructor\Data\ResponseModel;

class ResponseModelTestObject
{
    public string $name = 'old';
}

it('returns a new response model when updating property values', function () {
    $instance = new ResponseModelTestObject();
    $model = makeAnyResponseModel($instance);
    assert($model instanceof ResponseModel);

    $updated = $model->withPropertyValues(['name' => 'new']);

    expect($model->instance()->name)->toBe('old');
    expect($updated->instance()->name)->toBe('new');
});
