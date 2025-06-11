<?php

use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;

it('displays single string column correctly', function () {
    $output = Console::columns(['Sample text'], 80);
    expect($output)->toBe('Sample text' . Color::RESET . ' ');
});

it('aligns single column text to the left by default', function () {
    $output = Console::columns([[-1, 'Left aligned', STR_PAD_LEFT]], 80);
    expect($output)->toBe(
        str_pad('Left aligned', 80, ' ', STR_PAD_LEFT)
        . Color::RESET . ' '
    );
});

it('aligns single column text to the right', function () {
    $output = Console::columns([[-1, 'Right aligned', STR_PAD_RIGHT]], 80);
    expect($output)->toBe(
        str_pad('Right aligned', 80, ' ', STR_PAD_RIGHT)
        . Color::RESET . ' '
    );
});

it('truncates and appends ellipsis to long text based on maxWidth', function () {
    $longText = str_repeat('A', 100);
    $output = Console::columns([[-1, $longText]], 80);
    $expected = str_pad(substr($longText, 0, 77) . '...' . Color::RESET . ' ', 80);
    expect($output)->toBe($expected);
});

it('handles mixed array of strings and column specifications', function () {
    $output = Console::columns(['Static text', [-1, 'Dynamic text', STR_PAD_RIGHT]], 80);
    expect($output)->toBe(
        'Static text' . Color::RESET . ' '
        . 'Dynamic text'
        . '                                                    ' . Color::RESET . ' ');
});

it('ensures minWidth of 80 if maxWidth is less', function () {
    $output = Console::columns([[-1, 'Min width enforced', STR_PAD_RIGHT]], 10);
    expect(strlen($output))->toBe(80 + strlen(Color::RESET . ' '));
});