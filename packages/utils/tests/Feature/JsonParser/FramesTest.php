<?php declare(strict_types=1);

use Cognesy\Utils\Json\Partial\ArrayFrame;
use Cognesy\Utils\Json\Partial\ObjectFrame;

uses()->group('frames');

test('ArrayFrame collects values in order', function () {
    $f = new ArrayFrame();
    $f->addValue(1);
    $f->addValue('x');
    expect($f->getValue())->toBe([1, 'x']);
});

test('ObjectFrame adds via pending key, synthesizes empty key when missing', function () {
    $f = new ObjectFrame();
    // add without key first -> key ''
    $f->addValue(1);
    $f->setPendingKey('a');
    $f->addValue('x');
    expect($f->getValue())->toBe(['' => 1, 'a' => 'x']);
});

test('ObjectFrame closes pending key with empty string on EOF', function () {
    $f = new ObjectFrame();
    $f->setPendingKey('k');
    $f->closeIfPending();
    expect($f->getValue())->toBe(['k' => '']);
});
