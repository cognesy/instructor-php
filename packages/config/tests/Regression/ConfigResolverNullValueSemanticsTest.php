<?php declare(strict_types=1);

use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Providers\ArrayConfigProvider;

// Guards regression from instructor-zp12 (explicit null treated as missing).
it('returns null for existing null value in strict get()', function () {
    $resolver = ConfigResolver::using(
        new ArrayConfigProvider(['feature' => ['flag' => null]]),
    );

    expect($resolver->has('feature.flag'))->toBeTrue();
    expect($resolver->get('feature.flag'))->toBeNull();
});

it('does not fall through to lower-priority providers when first provider has null value', function () {
    $primary = new ArrayConfigProvider(['feature' => ['flag' => null]]);
    $secondary = new ArrayConfigProvider(['feature' => ['flag' => 'enabled']]);
    $resolver = ConfigResolver::using($primary)->then($secondary);

    expect($resolver->get('feature.flag'))->toBeNull();
});

it('does not apply default when key exists with null value', function () {
    $resolver = ConfigResolver::using(
        new ArrayConfigProvider(['feature' => ['flag' => null]]),
    );

    expect($resolver->get('feature.flag', 'fallback'))->toBeNull();
});

