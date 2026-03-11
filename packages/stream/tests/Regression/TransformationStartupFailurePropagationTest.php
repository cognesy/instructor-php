<?php declare(strict_types=1);

use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;

it('propagates generator startup failures from Transformation::execute', function () {
    $source = (function () {
        throw new TypeError('generator startup failed');
        yield 1;
    })();

    (new Transformation())
        ->withInput($source)
        ->execute();
})->throws(TypeError::class, 'generator startup failed');

it('propagates generator startup failures from TransformationStream::getCompleted', function () {
    $source = (function () {
        throw new TypeError('generator startup failed');
        yield 1;
    })();

    TransformationStream::from($source)->getCompleted();
})->throws(TypeError::class, 'generator startup failed');
