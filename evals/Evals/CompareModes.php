<?php
namespace Cognesy\Evals\Evals;

use Closure;
use Cognesy\Evals\Evals\Contracts\CanExecuteExperiment;
use Cognesy\Evals\Evals\Data\EvalInput;
use Cognesy\Evals\Evals\Data\EvalOutput;
use Cognesy\Instructor\Enums\Mode;
use Exception;

class CompareModes {
    private array $exceptions = [];
    private array $responses = [];
    private Display $display;
    private string|array|object $schema;
    /** @var class-string */
    private string $executor;
    private string|array $messages;
    private Closure $evalFn;

    public function __construct(
        string|array $messages,
        string|array|object $schema,
        string       $executorClass,
        Closure      $evalFn,
    ) {
        $this->messages = $messages;
        $this->schema = $schema;
        $this->executor = $executorClass;
        $this->evalFn = $evalFn;
        $this->display = new Display();
    }

    // PUBLIC //////////////////////////////////////////////////

    public function executeAll(array $connections, array $modes, array $streamingModes) : array {
        foreach ($streamingModes as $isStreamed) {
            foreach ($modes as $mode) {
                foreach ($connections as $connection) {
                    $this->display->before($mode, $connection, $isStreamed);
                    $evalResponse = $this->execute($connection, $mode, $isStreamed);
                    $this->responses[] = $evalResponse;
                    $this->display->after($evalResponse);
                }
            }
        }
        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }
        return $this->responses;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function execute(string $connection, Mode $mode, bool $isStreamed) : EvalOutput {
        $key = $this->makeKey($connection, $mode, $isStreamed);
        try {
            $evalInput = new EvalInput(
                messages: $this->messages,
                schema: $this->schema,
                mode: $mode,
                connection: $connection,
                isStreamed: $isStreamed,
            );
            // execute and measure time
            $time = microtime(true);
            /** @var CanExecuteExperiment $execution */
            $execution = ($this->executor)::executeFor($evalInput);
            $llmResponse = $execution->getLLMResponse();
            $evalInput->withResponse($llmResponse);
            $answer = $execution->getAnswer();
            $timeElapsed = microtime(true) - $time;
            $isCorrect = ($this->evalFn)($evalInput);

            $evalResponse = new EvalOutput(
                id: $key,
                notes: $llmResponse->content(),
                isCorrect: $isCorrect,
                timeElapsed: $timeElapsed,
                inputTokens: $llmResponse->usage()->inputTokens,
                outputTokens: $llmResponse->usage()->outputTokens,
            );
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->exceptions[$key] = $e;
            $evalResponse = new EvalOutput(
                id: $key,
                notes: '',
                isCorrect: false,
                timeElapsed: $timeElapsed,
                exception: $e,
            );
        }
        return $evalResponse;
    }

    private function makeKey(string $connection, Mode $mode, bool $isStreamed) : string {
        return $connection.'::'.$mode->value.'::'.($isStreamed?'streamed':'sync');
    }
}
