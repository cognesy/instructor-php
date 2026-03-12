<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Data\Mount;
use Cognesy\Sandbox\Value\Argv;

describe('Mount', function () {
    it('formats volume argument as host:container:options', function () {
        $mount = new Mount('/host/data', '/container/data', 'ro');
        expect($mount->toVolumeArg())->toBe('/host/data:/container/data:ro');
    });

    it('exposes individual components', function () {
        $mount = new Mount('/src', '/dest', 'rw');
        expect($mount->host())->toBe('/src');
        expect($mount->container())->toBe('/dest');
        expect($mount->options())->toBe('rw');
    });
});

describe('Argv', function () {
    it('creates from array and converts back', function () {
        $argv = Argv::of(['php', '-v']);
        expect($argv->toArray())->toBe(['php', '-v']);
    });

    it('returns new instance when appending', function () {
        $a = Argv::of(['echo']);
        $b = $a->with('hello');

        expect($a->toArray())->toBe(['echo']);
        expect($b->toArray())->toBe(['echo', 'hello']);
    });
});
