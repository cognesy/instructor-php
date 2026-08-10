<?php declare(strict_types=1);

use Cognesy\Utils\Files;

/**
 * Files::files() and Files::directories() validated their argument inside a generator
 * body. A generator body does not execute until the first iteration, so calling them
 * with a bad path handed back a Generator and threw nothing; the InvalidArgumentException
 * surfaced later at the foreach - or never, if the caller only counted or discarded the
 * result. Validation now happens eagerly, before the generator is created.
 *
 * Lives in Integration rather than Regression because it does real filesystem I/O,
 * which the repo keeps out of the fast lanes.
 */

beforeEach(function () {
    $this->root = sys_get_temp_dir() . '/utils-files-' . bin2hex(random_bytes(6));
    mkdir($this->root . '/sub/nested', 0777, true);
    file_put_contents($this->root . '/top.txt', 'a');
    file_put_contents($this->root . '/sub/mid.txt', 'b');
    file_put_contents($this->root . '/sub/nested/deep.txt', 'c');
});

afterEach(function () {
    Files::removeDirectory($this->root);
});

it('throws immediately when files() is called with a non-directory', function () {
    expect(fn() => Files::files('/definitely/not/a/directory'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws immediately when directories() is called with a non-directory', function () {
    expect(fn() => Files::directories('/definitely/not/a/directory'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws before any iteration happens', function () {
    // Pinning the actual defect: the call itself must throw, not the foreach.
    $threwOnCall = false;
    try {
        Files::files('/definitely/not/a/directory');
    } catch (InvalidArgumentException) {
        $threwOnCall = true;
    }
    expect($threwOnCall)->toBeTrue();
});

it('throws for a path that exists but is a file', function () {
    expect(fn() => Files::files($this->root . '/top.txt'))
        ->toThrow(InvalidArgumentException::class);
});

it('yields every file recursively', function () {
    $names = [];
    foreach (Files::files($this->root) as $file) {
        $names[] = $file->getFilename();
    }
    sort($names);
    expect($names)->toBe(['deep.txt', 'mid.txt', 'top.txt']);
});

it('yields every directory recursively and no files', function () {
    $names = [];
    foreach (Files::directories($this->root) as $dir) {
        $names[] = $dir->getFilename();
        expect($dir->isDir())->toBeTrue();
    }
    sort($names);
    expect($names)->toBe(['nested', 'sub']);
});

it('yields nothing for an empty directory', function () {
    $empty = $this->root . '/empty';
    mkdir($empty);
    expect(iterator_to_array(Files::files($empty), false))->toBe([]);
    expect(iterator_to_array(Files::directories($empty), false))->toBe([]);
});
