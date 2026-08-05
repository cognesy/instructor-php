<?php declare(strict_types=1);

use Cognesy\Polyglot\Support\Pricing\Cost;
use Cognesy\Polyglot\Support\Redaction\SensitiveDataRedactor;
use Cognesy\Polyglot\Support\Retry\RetryBackoff;
use Cognesy\Polyglot\Support\Retry\RetryJitter;
use Cognesy\Polyglot\Support\Retry\RetryPolicyInvariants;

/**
 * Pins the back-compat shim in `src/deprecated_aliases.php` (instructor-eexl.12).
 *
 * Four classes moved out of `Inference\` into `Support\`, and `Cost` moved from the top-level
 * `Polyglot\Pricing\` so that `Support\` is the package's single answer for things neither
 * subsystem owns. The old FQCNs are public surface -- `RetryJitter` is the declared type of
 * `InferenceRetryPolicy::$jitterMode` and `Cost` is the documented return of every cost
 * calculator, so user code can legitimately name both. Without these assertions the shim is
 * one careless `composer.json` edit away from silently disappearing: nothing else in the suite
 * references the old names, so every other test would keep passing.
 *
 * The old names are written as STRINGS on purpose. Importing them would make this file itself
 * depend on the aliases resolving at compile time, which is the thing under test.
 */

const MOVED_CLASSES = [
    'Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor' => SensitiveDataRedactor::class,
    'Cognesy\Polyglot\Inference\Config\RetryBackoff' => RetryBackoff::class,
    'Cognesy\Polyglot\Inference\Config\RetryJitter' => RetryJitter::class,
    'Cognesy\Polyglot\Inference\Config\RetryPolicyInvariants' => RetryPolicyInvariants::class,
    'Cognesy\Polyglot\Pricing\Cost' => Cost::class,
];

it('resolves every moved class under its old FQCN', function (string $old, string $new) {
    expect(class_exists($old))->toBeTrue();
    // Not merely "a class exists" -- class_alias makes it the SAME class, which a deprecated
    // subclass shim would not. Anything weaker would let `instanceof` break in one direction.
    expect((new ReflectionClass($old))->getName())->toBe($new);
})->with(array_map(
    static fn(string $old, string $new) => [$old, $new],
    array_keys(MOVED_CLASSES),
    array_values(MOVED_CLASSES),
));

it('preserves enum case identity across the alias', function () {
    // Enums cannot be extended, so class_alias is the ONLY shim available here. Case identity
    // is what user code actually compares against: `$policy->jitterMode === RetryJitter::Full`.
    $old = 'Cognesy\Polyglot\Inference\Config\RetryJitter';

    expect(constant($old . '::Full'))->toBe(RetryJitter::Full);
    expect(constant($old . '::None'))->toBe(RetryJitter::None);
    expect(constant($old . '::Equal'))->toBe(RetryJitter::Equal);
    expect(RetryJitter::Full)->toBeInstanceOf($old);
});

it('keeps redaction behaviour reachable under the old FQCN', function () {
    // SECURITY-RELEVANT. Callers that pinned the old name must still redact, not merely resolve.
    $old = 'Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor';

    expect($old::redactHeaders(['Authorization' => 'Bearer secret']))
        ->toBe(SensitiveDataRedactor::redactHeaders(['Authorization' => 'Bearer secret']))
        ->not->toBe(['Authorization' => 'Bearer secret']);
    expect($old::redactUrl('https://api.example.com/v1?api_key=secret'))
        ->toBe(SensitiveDataRedactor::redactUrl('https://api.example.com/v1?api_key=secret'))
        ->not->toContain('secret');
});
