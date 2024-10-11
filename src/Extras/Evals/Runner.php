<?php
namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Extras\Evals\Data\ExperimentData;
use Cognesy\Instructor\Extras\Evals\Inference\InferenceParams;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Exception;
use Generator;

class Runner {
    private array $exceptions = [];
    private array $responses = [];
    private Display $display;
    private CanExecuteExperiment $runner;
    private CanEvaluateExperiment $evaluation;

    private ExperimentData $data;

    public function __construct(
        ExperimentData $data,
        CanExecuteExperiment $runner,
        CanEvaluateExperiment $evaluation,
    ) {
        $this->data = $data;
        $this->runner = $runner;
        $this->evaluation = $evaluation;
        $this->display = new Display();
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @param Generator<InferenceParams> $combinations
     * @return array<Experiment>
     */
    public function execute(
        Generator $combinations
    ) : array {
        foreach ($combinations as $params) {
            $this->display->before($params->mode, $params->connection, $params->isStreaming);
            $evaluation = $this->makeExperiment($params->connection, $params->mode, $params->isStreaming);
            $evaluation = $this->executeExperiment($evaluation);
            $this->responses[] = $evaluation;
            $this->display->after($evaluation);
        }

        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->responses;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function executeExperiment(Experiment $experiment) : Experiment {
        try {
            // execute and measure time
            $time = microtime(true);

            $this->runner->execute($experiment);
            $llmResponse = $this->runner->getLLMResponse();
            $experiment->withResponse($llmResponse);

            $timeElapsed = microtime(true) - $time;

            $evalResponse = $experiment->withOutput(
                notes: $llmResponse->content(),
                metric: $this->evaluation->evaluate($experiment),
                timeElapsed: $timeElapsed,
                inputTokens: $llmResponse->usage()->inputTokens,
                outputTokens: $llmResponse->usage()->outputTokens,
            );
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->exceptions[$experiment->id] = $e;
            $evalResponse = $experiment->withOutput(
                notes: '',
                metric: new BooleanCorrectness(false),
                timeElapsed: $timeElapsed,
                exception: $e,
            );
        }
        return $evalResponse;
    }

    private function makeExperiment(string $connection, Mode $mode, bool $isStreamed) : Experiment {
        return (new Experiment(
            id: $this->makeKey($connection, $mode, $isStreamed),
            connection: $connection,
            mode: $mode,
            isStreamed: $isStreamed,
        ))->withExperimentData($this->data);
    }

    private function makeKey(string $connection, Mode $mode, bool $isStreamed) : string {
        return $connection.'::'.$mode->value.'::'.($isStreamed ? 'streamed' : 'sync');
    }
}
