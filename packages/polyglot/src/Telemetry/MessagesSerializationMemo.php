<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Telemetry;

use Cognesy\Messages\Messages;
use WeakReference;

/**
 * Memoizes the most recent `Messages::toArray()` on object identity.
 *
 * The telemetry path serialises the conversation four times per non-retried request --
 * `InferenceTelemetry::execution()` at started and completed, `attempt()` at attempt-started
 * and attempt-succeeded -- and all four see the *same* `Messages` instance:
 * `withTelemetryCorrelation()` builds a new `InferenceRequest` but carries the conversation
 * object over unchanged. Serialisation is >95% of envelope cost on medium and long
 * conversations (115 µs for a 128 KB one), so three of the four are repeat work.
 *
 * **Keying on identity is a correctness requirement, not a convenience.** A session- or
 * request-scoped cache would be wrong: `InferenceExecutionSession::buildLengthRecoveryRequest()`
 * rewrites the conversation mid-session on a `LengthRecovery::ContinueWithPrompt` retry, and
 * a scoped cache would then serve the pre-rewrite messages. `Messages` is `final readonly`
 * and holds `final readonly` `Message`s, so identity implies identical content and a
 * rewritten conversation is always a new key.
 *
 * **Two entries, deliberately.** The obvious shape is a `WeakMap<Messages, array>`, and it
 * was measured: it wins the same amount on the four-site path but retains a serialised copy
 * of every live conversation, which raised peak memory ~25% and made the *single*-site path
 * (a listener on `InferenceCompleted` only) measurably slower under the extra allocator
 * pressure. Fixed slots keyed by `WeakReference` capture the whole win at O(1) retention, and
 * do not extend the lifetime of the conversations they point at.
 *
 * Two rather than one, because the structured-output path interleaves two conversations: a
 * `packages/instructor` request telemetry envelope, then the *materialized* inference
 * conversation for the nested `Inference` call, then instructor's response envelope on the
 * original conversation again. One slot thrashes on that pattern (7 serialisations down to
 * 3); two hold both conversations and reach the floor (2). A third would buy nothing
 * measured. Deeper nesting simply misses and re-serialises -- a lost optimisation, never a
 * wrong answer.
 */
final class MessagesSerializationMemo
{
    /** @var WeakReference<Messages>|null */
    private static ?WeakReference $key0 = null;

    /** @var list<array<array-key, mixed>> */
    private static array $value0 = [];

    /** @var WeakReference<Messages>|null */
    private static ?WeakReference $key1 = null;

    /** @var list<array<array-key, mixed>> */
    private static array $value1 = [];

    /**
     * Monotonic count of conversations actually serialised, i.e. memo misses.
     *
     * Diagnostic, and the only way to assert the memo works: `Messages` and `Message` are
     * both `final readonly`, so neither can be subclassed into a spy. Tests snapshot this
     * before and after a request rather than resetting it, so they stay correct in a shared
     * process.
     */
    private static int $serialisations = 0;

    /** @return list<array<array-key, mixed>> */
    public static function toArray(Messages $messages): array {
        if (self::$key0?->get() === $messages) {
            return self::$value0;
        }
        if (self::$key1?->get() === $messages) {
            return self::$value1;
        }

        self::$serialisations++;

        // Miss: shift slot 0 down and install at 0. No promotion on a slot-1 hit -- for the
        // A,B,A and A,B,A,B patterns these sites actually produce, a plain shift is already
        // stable, and swapping on every hit would cost more than it saves.
        self::$key1 = self::$key0;
        self::$value1 = self::$value0;
        self::$key0 = WeakReference::create($messages);
        self::$value0 = $messages->toArray();

        return self::$value0;
    }

    /** Number of real serialisations performed since process start. */
    public static function serialisationCount(): int {
        return self::$serialisations;
    }
}
