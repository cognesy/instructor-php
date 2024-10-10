<?php
namespace Cognesy\Instructor\Extras\Evals;

use Closure;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Console\Display;
use Cognesy\Instructor\Extras\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Extras\Evals\Data\EvalOutput;
use Cognesy\Instructor\Extras\Evals\Mappings\ConnectionModes;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanMetric;
use Exception;
use Generator;

class Evaluator {
    private array $exceptions = [];
    private array $responses = [];
    private Display $display;
    private string|array|object $schema;
    private CanExecuteExperiment $runner;
    private string|array $messages;
    private Closure $evalFn;

    public function __construct(
        string|array         $messages,
        string|array|object  $schema,
        CanExecuteExperiment $runner,
        Closure              $evalFn,
    ) {
        $this->messages = $messages;
        $this->schema = $schema;
        $this->runner = $runner;
        $this->evalFn = $evalFn;
        $this->display = new Display();
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @param Generator<ConnectionModes> $combinations
     * @return array
     */
    public function execute(
        Generator $combinations
    ) : array {
        foreach ($combinations as $params) {
            $this->display->before($params->mode, $params->connection, $params->isStreaming);
            $evalInput = $this->makeEvalInput($params->connection, $params->mode, $params->isStreaming);
            $evalResponse = $this->executeSingle($evalInput);
            $this->responses[] = $evalResponse;
            $this->display->after($evalResponse);
        }

        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->responses;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function executeSingle(EvalInput $evalInput) : EvalOutput {
        try {
            // execute and measure time
            $time = microtime(true);

            $this->runner->withEvalInput($evalInput);
            $this->runner->execute();
            $llmResponse = $this->runner->getLLMResponse();
            $evalInput->withResponse($llmResponse);

            $timeElapsed = microtime(true) - $time;
            $isCorrect = ($this->evalFn)($evalInput);

            $evalResponse = new EvalOutput(
                id: $evalInput->id,
                notes: $llmResponse->content(),
                metric: new BooleanMetric($isCorrect),
                timeElapsed: $timeElapsed,
                inputTokens: $llmResponse->usage()->inputTokens,
                outputTokens: $llmResponse->usage()->outputTokens,
            );
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->exceptions[$evalInput->id] = $e;
            $evalResponse = new EvalOutput(
                id: $evalInput->id,
                notes: '',
                metric: new BooleanMetric(false),
                timeElapsed: $timeElapsed,
                exception: $e,
            );
        }
        return $evalResponse;
    }

    private function makeEvalInput(string $connection, Mode $mode, bool $isStreamed) : EvalInput {
        return new EvalInput(
            id: $this->makeKey($connection, $mode, $isStreamed),
            messages: $this->messages,
            schema: $this->schema,
            mode: $mode,
            connection: $connection,
            isStreamed: $isStreamed,
        );
    }

    private function makeKey(string $connection, Mode $mode, bool $isStreamed) : string {
        return $connection.'::'.$mode->value.'::'.($isStreamed ? 'streamed' : 'sync');
    }
}
