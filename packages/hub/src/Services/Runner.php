<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\ErrorEvent;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Views\RunnerView;
use Exception;

class Runner
{
    public int $correct = 0;
    public int $incorrect = 0;
    public int $total = 0;
    public float $timeStart = 0;
    public float $totalTime = 0;
    /** @var ErrorEvent[] */
    public array $errors = [];

    public function __construct(
        public ExampleRepository $examples,
        public bool              $displayErrors,
        public int               $stopAfter,
        public bool              $stopOnError,
    ) {}

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function runSingle(Example $example) : void {
        try {
            include $example->runPath;
        } catch (Exception $e) {
            (new RunnerView)->executionError($example, $e);
        }
    }

    public function runAll(int $index) : void {
        $this->examples->forEachExample(function(Example $example) use ($index) {
            return $this->runFile($example, $index);
        });
        (new RunnerView)->stats($this->correct, $this->incorrect, $this->total);
        (new RunnerView)->displayErrors($this->errors, $this->displayErrors);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private function runFile(Example $example, int $index) : bool {
        if ($index > 0 && $example->index < $index) {
            return true;
        }
        (new RunnerView)->runStart($example);
        $this->timeStart = microtime(true);
        $output = $this->execute($example->runPath);
        $this->totalTime = $this->recordTimeElapsed();
        $hasErrors = $this->hasErrors($output);
        $result = $this->processOutput($output, $example);
        (new RunnerView)->renderOutput($hasErrors, $this->totalTime);
        if (!$result) {
            if (!empty($this->errors)) {
                (new RunnerView)->onError();
            } else {
                (new RunnerView)->onStop();
            }
        }
        return $result;
    }

    private function execute(string $runPath) : string {
        ob_start();
        try {
            $command = 'php ' . $runPath . ' 2>&1';
            $output = shell_exec($command);
        } catch (Exception $e) {
            $output = $e->getMessage();
        }
        $bufferedOutput = ob_get_contents();
        ob_end_clean();
        return $output . $bufferedOutput;
    }

    private function processOutput(string $output, Example $example) : bool
    {
        if ($this->hasErrors($output)) {
            $this->errors[$example->name][] = new ErrorEvent($example->name, $output);
            $this->incorrect++;
            if ($this->stopOnError) {
                return false;
            }
        } else {
            $this->correct++;
        }
        $this->total++;
        if (($this->stopAfter > 0) && ($this->total >= $this->stopAfter)) {
            return false;
        }
        return true;
    }

    private function recordTimeElapsed() : float {
        return microtime(true) - $this->timeStart;
    }

    private function hasErrors(string $output) : bool {
        return strpos($output, 'Fatal error') !== false;
    }
}