<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Enums;

/**
 * What to do about an attempt that produced a response the caller cannot use.
 *
 * Returned by InferenceRetryLoop::actionForFailedResponse(). Enum cases are singletons, so
 * this crosses no allocation boundary -- but it is reached at most once per attempt in any
 * case, never from the per-delta path.
 */
enum InferenceFailureAction: string
{
    /** Hit the length limit and the policy allows another go; the request is rewritten. */
    case RecoverFromLength = 'recover_from_length';

    /** Blocked by the provider's content filter. Terminal, and not retryable by policy. */
    case ContentFilterBlocked = 'content_filter_blocked';

    /** Any other unusable finish reason. Terminal. */
    case Terminal = 'terminal';
}
