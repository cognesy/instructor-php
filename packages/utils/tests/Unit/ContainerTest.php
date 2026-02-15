<?php declare(strict_types=1);

use Cognesy\Utils\Container\Container;
use Cognesy\Utils\Container\Exceptions\ContainerException;
use Cognesy\Utils\Container\Exceptions\NotFoundException;
use Cognesy\Utils\Container\PsrContainer;
use Cognesy\Utils\Container\SimpleContainer;
use Psr\Container\ContainerInterface;

// ---------------------------------------------------------------------------
// SimpleContainer — transient factories
// ---------------------------------------------------------------------------

test('SimpleContainer transient factory creates new instance each time', function () {
    $c = new SimpleContainer();
    $c->set('counter', fn () => new stdClass());

    $a = $c->get('counter');
    $b = $c->get('counter');

    expect($a)->not->toBe($b);
});

test('SimpleContainer transient factory receives container', function () {
    $c = new SimpleContainer();
    $c->instance('greeting', (object) ['value' => 'hello']);
    $c->set('upper', fn (Container $c) => (object) ['value' => strtoupper($c->get('greeting')->value)]);

    expect($c->get('upper')->value)->toBe('HELLO');
});

// ---------------------------------------------------------------------------
// SimpleContainer — singletons
// ---------------------------------------------------------------------------

test('SimpleContainer singleton factory called once and cached', function () {
    $c = new SimpleContainer();
    $calls = 0;
    $c->singleton('service', function () use (&$calls) {
        $calls++;
        return new stdClass();
    });

    $a = $c->get('service');
    $b = $c->get('service');

    expect($a)->toBe($b);
    expect($calls)->toBe(1);
});

test('SimpleContainer singleton factory receives container for recursive resolution', function () {
    $c = new SimpleContainer();
    $c->instance('dep', (object) ['v' => 42]);
    $c->singleton('service', fn (Container $c) => (object) ['dep' => $c->get('dep')]);

    expect($c->get('service')->dep->v)->toBe(42);
});

// ---------------------------------------------------------------------------
// SimpleContainer — instance
// ---------------------------------------------------------------------------

test('SimpleContainer instance returns exact object', function () {
    $c = new SimpleContainer();
    $obj = new stdClass();
    $c->instance('thing', $obj);

    expect($c->get('thing'))->toBe($obj);
});

// ---------------------------------------------------------------------------
// SimpleContainer — has()
// ---------------------------------------------------------------------------

test('SimpleContainer has() returns true for all binding types', function () {
    $c = new SimpleContainer();
    $c->set('transient', fn () => new stdClass());
    $c->singleton('single', fn () => new stdClass());
    $c->instance('direct', new stdClass());

    expect($c->has('transient'))->toBeTrue();
    expect($c->has('single'))->toBeTrue();
    expect($c->has('direct'))->toBeTrue();
    expect($c->has('missing'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// SimpleContainer — NotFoundException
// ---------------------------------------------------------------------------

test('SimpleContainer throws NotFoundException for unknown id', function () {
    $c = new SimpleContainer();
    $c->get('nope');
})->throws(NotFoundException::class);

// ---------------------------------------------------------------------------
// SimpleContainer — overwrite semantics
// ---------------------------------------------------------------------------

test('SimpleContainer singleton overwrites prior transient', function () {
    $c = new SimpleContainer();
    $c->set('x', fn () => (object) ['type' => 'transient']);
    $c->singleton('x', fn () => (object) ['type' => 'singleton']);

    expect($c->get('x')->type)->toBe('singleton');
});

test('SimpleContainer instance overwrites prior singleton', function () {
    $c = new SimpleContainer();
    $c->singleton('x', fn () => (object) ['type' => 'singleton']);
    $c->instance('x', (object) ['type' => 'instance']);

    expect($c->get('x')->type)->toBe('instance');
});

test('SimpleContainer set overwrites prior instance', function () {
    $c = new SimpleContainer();
    $c->instance('x', (object) ['type' => 'instance']);
    $c->set('x', fn () => (object) ['type' => 'transient']);

    expect($c->get('x')->type)->toBe('transient');
});

// ---------------------------------------------------------------------------
// SimpleContainer — circular dependency detection
// ---------------------------------------------------------------------------

test('SimpleContainer detects direct circular dependency', function () {
    $c = new SimpleContainer();
    $c->singleton('A', fn (Container $c) => $c->get('A'));

    $c->get('A');
})->throws(ContainerException::class, 'Circular dependency detected: A -> A');

test('SimpleContainer detects indirect circular dependency', function () {
    $c = new SimpleContainer();
    $c->singleton('A', fn (Container $c) => $c->get('B'));
    $c->singleton('B', fn (Container $c) => $c->get('A'));

    $c->get('A');
})->throws(ContainerException::class, 'Circular dependency detected: A -> B -> A');

test('SimpleContainer circular detection does not false-positive on resolved singletons', function () {
    $c = new SimpleContainer();
    $c->singleton('dep', fn () => (object) ['v' => 1]);
    $c->singleton('A', fn (Container $c) => (object) ['dep' => $c->get('dep')]);
    $c->singleton('B', fn (Container $c) => (object) ['dep' => $c->get('dep')]);

    // dep resolved twice from different roots — no circular
    expect($c->get('A')->dep->v)->toBe(1);
    expect($c->get('B')->dep->v)->toBe(1);
});

// ---------------------------------------------------------------------------
// PsrContainer — reads from inner
// ---------------------------------------------------------------------------

test('PsrContainer reads from inner PSR-11 container', function () {
    $inner = new class implements ContainerInterface {
        public function get(string $id): mixed { return (object) ['from' => 'inner']; }
        public function has(string $id): bool { return $id === 'svc'; }
    };

    $c = new PsrContainer($inner);

    expect($c->has('svc'))->toBeTrue();
    expect($c->get('svc')->from)->toBe('inner');
});

test('PsrContainer has() combines inner and local', function () {
    $inner = new class implements ContainerInterface {
        public function get(string $id): mixed { return new stdClass(); }
        public function has(string $id): bool { return $id === 'inner_only'; }
    };

    $c = new PsrContainer($inner);
    $c->instance('local_only', new stdClass());

    expect($c->has('inner_only'))->toBeTrue();
    expect($c->has('local_only'))->toBeTrue();
    expect($c->has('neither'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// PsrContainer — local overlay priority
// ---------------------------------------------------------------------------

test('PsrContainer local overlay takes priority over inner', function () {
    $inner = new class implements ContainerInterface {
        public function get(string $id): mixed { return (object) ['from' => 'inner']; }
        public function has(string $id): bool { return true; }
    };

    $c = new PsrContainer($inner);
    $c->instance('svc', (object) ['from' => 'local']);

    expect($c->get('svc')->from)->toBe('local');
});

// ---------------------------------------------------------------------------
// PsrContainer — write operations
// ---------------------------------------------------------------------------

test('PsrContainer supports set, singleton, instance writes', function () {
    $inner = new class implements ContainerInterface {
        public function get(string $id): mixed { throw new \RuntimeException('not found'); }
        public function has(string $id): bool { return false; }
    };

    $c = new PsrContainer($inner);

    $c->set('transient', fn () => (object) ['t' => true]);
    $c->singleton('single', fn () => (object) ['s' => true]);
    $c->instance('direct', (object) ['d' => true]);

    expect($c->get('transient')->t)->toBeTrue();
    expect($c->get('single')->s)->toBeTrue();
    expect($c->get('direct')->d)->toBeTrue();
});
