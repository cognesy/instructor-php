<?php
namespace Cognesy\Evals\LLMModes;

use Closure;
use Cognesy\Instructor\Enums\Mode;
use Exception;

class CompareModes {
    private array $exceptions = [];
    private array $responses = [];
    private Display $display;
    private Modes $modes;

    public function __construct(
        private string|array $query,
        private Closure $evalFn,
        array $schema = [],
        bool $debug = false,
    ) {
        $this->display = new Display();
        $this->modes = new Modes(schema: $schema, debug: $debug);
    }

    // PUBLIC //////////////////////////////////////////////////

    public function executeAll(array $connections, array $modes, array $streamingModes = [false, true]) : array {
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

    private function execute(string $connection, Mode $mode, bool $isStreamed) : EvalResponse {
        $key = $this->makeKey($connection, $mode, $isStreamed);
        try {
            $time = microtime(true);
            $answer = $this->modes->callInferenceFor($this->query, $mode, $connection, $this->modes->schema(), $isStreamed);
            $timeElapsed = microtime(true) - $time;
            $evalRequest = new EvalRequest(
                $answer, $this->query, $this->modes->schema(), $mode, $connection, $isStreamed
            );
            $isCorrect = ($this->evalFn)($evalRequest);
            $evalResponse = new EvalResponse(
                id: $key,
                answer: $answer,
                isCorrect: $isCorrect,
                timeElapsed: $timeElapsed,
            );
        } catch(Exception $e) {
            $timeElapsed = microtime(true) - $time;
            $this->exceptions[$key] = $e;
            $evalResponse = new EvalResponse(
                id: $key,
                answer: '',
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
