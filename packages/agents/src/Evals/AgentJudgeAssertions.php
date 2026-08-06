<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class AgentJudgeAssertions
{
    /** @param callable(): AgentRun $run */
    public function __construct(
        private ?CanJudgeAgentEval $judge,
        private AgentRun $run,
        private AssertionCollector $collector,
    ) {}

    public function factuality(string $reference): JudgeExpectation {
        return $this->make(JudgeCriterion::Factuality, 'Assess factual consistency.', reference: $reference);
    }

    public function summarizes(string $source): JudgeExpectation {
        return $this->make(JudgeCriterion::Summarizes, 'Assess summary coverage and faithfulness.', input: $source);
    }

    public function closedQa(string $question): JudgeExpectation {
        return $this->make(JudgeCriterion::ClosedQa, $question);
    }

    public function sql(string $reference): JudgeExpectation {
        return $this->make(JudgeCriterion::Sql, 'Assess SQL semantic equivalence.', reference: $reference);
    }

    private function make(
        JudgeCriterion $criterion,
        string $instruction,
        string $input = '',
        ?string $reference = null,
    ): JudgeExpectation {
        return new JudgeExpectation(
            judge: $this->judge,
            request: new JudgeRequest(
                criterion: $criterion->value . ': ' . $instruction,
                output: $this->run->reply(),
                run: $this->run,
                input: $input,
                reference: $reference,
            ),
            collector: $this->collector,
        );
    }
}
