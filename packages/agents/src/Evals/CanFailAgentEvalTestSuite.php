<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

/** Test-suite reporters may propagate their final assertion into the host test runner. */
interface CanFailAgentEvalTestSuite extends CanReportAgentEvals {}
