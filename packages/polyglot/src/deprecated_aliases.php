<?php declare(strict_types=1);

/**
 * Back-compat aliases for FQCNs that moved out of `Inference\` into `Support\`
 * (instructor-eexl.12).
 *
 * Four classes were never inference concepts -- `SensitiveDataRedactor` is used by the
 * embeddings side too, and `RetryJitter`'s own docblock describes it as "shared by inference
 * and embeddings retry policies". They lived under `Inference\` for historical reasons, which
 * forced every embeddings file that needed them to declare a dependency on the inference
 * subsystem. They now live in a namespace neither subsystem owns.
 *
 * `Cost` moved for the opposite reason: it was ALREADY neutral, sitting at a top-level
 * `Polyglot\Pricing\`. Adding `Support\` left the package with two answers to "where do things
 * neither subsystem owns live?", so `Cost` joined the one that is a rule rather than a
 * precedent. The two `FlatRateCostCalculator` classes did NOT move -- they share a name but
 * not a signature (five token categories against one), and `InferencePricing` /
 * `EmbeddingsPricing` carry different rate fields. Only `Cost` is genuinely shared.
 *
 * WHY AN ALIAS RATHER THAN A DEPRECATED SUBCLASS. Three of the four are classes and could
 * have been shimmed by leaving `class Old extends New {}` at the original path. `RetryJitter`
 * is an ENUM: enums cannot be extended, so no declaration-based shim exists for it, and it is
 * reachable from user code as the type of the public `InferenceRetryPolicy::$jitterMode`.
 * `class_alias()` is also strictly more faithful than a subclass would be -- the old name
 * becomes the SAME class, so `instanceof` holds in both directions and enum case identity
 * (`RetryJitter::Full === Support\Retry\RetryJitter::Full`) is preserved. One mechanism for
 * all four beats two mechanisms that behave differently.
 *
 * WHY LAZY. Calling `class_alias()` eagerly at include time would force all four target
 * classes to load in every process that touches this package, whether or not anything names
 * an old FQCN -- the exact "build what nobody consumes" shape this epic exists to remove.
 * Registering an autoloader instead costs one closure per process, and the callback only ever
 * runs on a class-not-found miss, where it does one isset() against a five-entry array.
 *
 * Remove at the next major.
 */

spl_autoload_register(static function (string $class): void {
    static $moved = [
        'Cognesy\\Polyglot\\Inference\\Core\\SensitiveDataRedactor' => \Cognesy\Polyglot\Support\Redaction\SensitiveDataRedactor::class,
        'Cognesy\\Polyglot\\Inference\\Config\\RetryBackoff' => \Cognesy\Polyglot\Support\Retry\RetryBackoff::class,
        'Cognesy\\Polyglot\\Inference\\Config\\RetryJitter' => \Cognesy\Polyglot\Support\Retry\RetryJitter::class,
        'Cognesy\\Polyglot\\Inference\\Config\\RetryPolicyInvariants' => \Cognesy\Polyglot\Support\Retry\RetryPolicyInvariants::class,
        'Cognesy\\Polyglot\\Pricing\\Cost' => \Cognesy\Polyglot\Support\Pricing\Cost::class,
    ];

    if (isset($moved[$class])) {
        class_alias($moved[$class], $class);
    }
});
