<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use RuntimeException;

/**
 * Thrown by `AgentLoopJudge::judge()` when the judge agent fails to follow the
 * terminal-submission protocol: no `submit_judgment` call was made, a second
 * `submit_judgment` call was blocked, a call in the run failed or was
 * malformed, or the judge run otherwise reported an error. The message
 * identifies which of these happened.
 *
 * `JudgeExpectation::resolve()` catches `Throwable` from `judge()` (this
 * exception included) and turns it into a gating `AssertionSeverity::Gate`
 * failure - it is never swallowed silently.
 */
final class JudgeProtocolException extends RuntimeException {}
