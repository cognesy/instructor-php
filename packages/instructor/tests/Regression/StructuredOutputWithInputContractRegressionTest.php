<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;

// Guards regression from instructor-9s3g (withInput contract mismatch leaking nested TypeError).
it('fails at StructuredOutput::withInput boundary for unsupported scalar inputs', function (mixed $input) {
    expect(fn() => (new StructuredOutput())->withInput($input))
        ->toThrow(\TypeError::class, 'StructuredOutput::withInput()');
})->with([
    'int' => [123],
    'float' => [12.3],
    'bool' => [true],
    'null' => [null],
]);
