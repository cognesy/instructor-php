<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ErrorEvent;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Utils\Color;
use Exception;

class Runner
{
    public int $correct = 0;
    public int $incorrect = 0;
    public int $total = 0;
    /** @var ErrorEvent[] */
    public array $errors;

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
            Cli::outln();
            Cli::out("[!] ", Color::DARK_YELLOW);
            Cli::outln("Failure while running example: {$example->name}", Color::RED);
            Cli::outln();
            Cli::outln("[Message]", Color::DARK_GRAY);
            Cli::outln($e->getMessage(), Color::GRAY);
            Cli::outln();
            Cli::outln("[Trace]", Color::DARK_GRAY);
            Cli::outln($e->getTraceAsString(), Color::GRAY);
        }
    }

    public function runAll() : void {
        $this->examples->forEachExample(function(Example $example) {
            return $this->runFile($example);
        });
        $this->stats();
        $this->displayErrors();
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    private function runFile(Example $example) : bool {
        // execute run.php and print the output to CLI
        Cli::grid([[3, "[.]", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        Cli::grid([[30, $example->name, STR_PAD_RIGHT, Color::WHITE]]);
        Cli::grid([[13, "> running ...", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        $timeStart = microtime(true);
        $output = $this->execute($example->runPath);
        // measure time elapsed
        $timeEnd = microtime(true);
        // process output
        $result = $this->processOutput($output, $example);
        // display time elapsed
        $totalTime = $timeEnd - $timeStart;
        Cli::out(" (", [Color::DARK_GRAY]);
        Cli::grid([[10, (round($totalTime, 2) . " sec"), STR_PAD_LEFT, Color::DARK_GRAY]]);
        Cli::outln(")", [Color::DARK_GRAY]);
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

    private function processOutput(string $output, Example $example) : bool {
        Cli::grid([[1, ">", STR_PAD_RIGHT, Color::DARK_GRAY]]);
        if (strpos($output, 'Fatal error') !== false) {
            $this->errors[$example->name][] = new ErrorEvent($example->name, $output);
            Cli::grid([[5, "ERROR", STR_PAD_LEFT, Color::RED]]);
            $this->incorrect++;
            if ($this->stopOnError) {
                Cli::outln();
                Cli::out("[!] ", Color::DARK_YELLOW);
                Cli::outln("Terminating - error encountered...", Color::YELLOW);
                return false;
            }
        } else {
            Cli::grid([[5, "OK", STR_PAD_RIGHT, Color::GREEN]]);
            $this->correct++;
        }
        $this->total++;
        if (($this->stopAfter > 0) && ($this->total >= $this->stopAfter)) {
            Cli::outln();
            Cli::out("[!] ", Color::DARK_YELLOW);
            Cli::outln("Terminating - set limit reached...", Color::YELLOW);
            return false;
        }
        return true;
    }

    public function stats() : void {
        $correctPercent = $this->percent($this->correct, $this->total);
        $incorrectPercent = $this->percent($this->incorrect, $this->total);
        Cli::outln();
        Cli::outln();
        Cli::outln("RESULTS:", [Color::YELLOW, Color::BOLD]);
        Cli::out("[+]", Color::GREEN);
        Cli::outln(" Correct runs ..... $this->correct ($correctPercent%)");
        Cli::out("[-]", Color::RED);
        Cli::outln(" Incorrect runs ... $this->incorrect ($incorrectPercent%)");
        Cli::outln("Total ................ $this->total (100%)", [Color::BOLD, Color::WHITE]);
        Cli::outln();
    }

    private function displayErrors() {
        if ($this->displayErrors && !empty($this->errors)) {
            Cli::outln();
            Cli::outln();
            Cli::outln("ERRORS:", [Color::YELLOW, Color::BOLD]);
            foreach ($this->errors as $name => $group) {
                Cli::outln("[$name]", Color::DARK_YELLOW);
                foreach ($group as $error) {
                    Cli::outln('---', Color::DARK_YELLOW);
                    Cli::margin($error->output, 4, Color::RED, Color::GRAY);
                    Cli::outln();
                }
            }
        }
    }

    private function percent(int $value, int $total) : int {
        return ($total == 0) ? 0 : round(($value / $total) * 100, 0);
    }
}