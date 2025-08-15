<?php

declare(strict_types=1);

use Cognesy\Doctor\Freeze\Freeze;
use Cognesy\Doctor\Freeze\FreezeCommand;
use Cognesy\Doctor\Freeze\FreezeConfig;

test('freeze file returns freeze command', function () {
    $command = Freeze::file('test.php');
    
    expect($command)->toBeInstanceOf(FreezeCommand::class);
});

test('freeze execute returns freeze command', function () {
    $command = Freeze::execute('ls -la');
    
    expect($command)->toBeInstanceOf(FreezeCommand::class);
});

test('freeze command builds correct command for file', function () {
    $command = Freeze::file('test.php')
        ->output('test.png')
        ->theme(FreezeConfig::THEME_DRACULA)
        ->window()
        ->showLineNumbers();

    $result = $command->buildCommandString();

    expect($result)
        ->toContain('freeze')
        ->toContain('--output')
        ->toContain('test.png')
        ->toContain('--theme')
        ->toContain('dracula')
        ->toContain('--window')
        ->toContain('--show-line-numbers')
        ->toContain('test.php');
});

test('freeze command builds correct command for execute', function () {
    $command = Freeze::execute('ls -la')
        ->output('terminal.png')
        ->background('#08163f')
        ->height(400);

    $result = $command->buildCommandString();

    expect($result)
        ->toContain('freeze')
        ->toContain('-x')
        ->toContain('ls -la')
        ->toContain('--output')
        ->toContain('terminal.png')
        ->toContain('--background')
        ->toContain('#08163f')
        ->toContain('--height')
        ->toContain('400');
});

test('freeze config constants', function () {
    expect(FreezeConfig::THEME_DRACULA)->toBe('dracula');
    expect(FreezeConfig::CONFIG_BASE)->toBe('base');
    expect(FreezeConfig::FORMAT_PNG)->toBe('png');
    
    expect(FreezeConfig::themes())->toContain('dracula');
    expect(FreezeConfig::configs())->toContain('base');
    expect(FreezeConfig::formats())->toContain('png');
});