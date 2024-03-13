<?php
namespace Cognesy\Instructor\Hub;

use Exception;

class Runner
{
    public $displayErrors = false;
    /** @var ErrorEvent[] */
    public array $errors;
    public int $correct = 0;
    public int $incorrect = 0;
    public int $total = 0;
    public string $output = '';
    private Hub $hub;
    private string $baseDir = '';
    public int $stopAfter = 2;
    public bool $stopOnError = true;

    public function __construct() {
        $this->hub = new Hub();
        $this->baseDir = $this->hub->getBaseDir();
    }

    public function run() : void {
        $this->toOutput("\033[1;33mExecuting all examples...\n");
        $directories = $this->hub->getExampleDirectories();
        // loop through the files and select only directories
        foreach ($directories as $file) {
            $this->toOutput(" \033[1;30m[.]\033[0m $file");
            // check if run.php exists in the directory
            if (!$this->hub->exampleExists($file)) {
                continue;
            }
            if (!$this->processFile($file)) {
                break;
            }
        }
        $this->stats();
    }

    private function processFile(mixed $file) : bool {
        // execute run.php and print the output to CLI
        $this->toOutput("\033[1;30m > running ...");
        $output = $this->execute($this->baseDir, $file);
        // process output
        return $this->processOutput($output, $file);
    }

    private function execute(string $dir, string $file) : string {
        ob_start();
        try {
            $output = shell_exec('php ' . $dir . '/' . $file . '/run.php 2>&1');
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $bufferedOutput = ob_get_contents();
        ob_end_clean();
        return $output . $bufferedOutput;
    }

    private function processOutput(string $output, string $file) : bool {
        if (strpos($output, 'Fatal error') !== false) {
            $this->errors[$file][] = new ErrorEvent($file, $output);
            $this->toOutput(" > \033[1;31mERROR\033[0m\n");
            $this->incorrect++;
            if ($this->stopOnError) {
                $this->toOutput(" [!] \033[1;33mTerminating - error encountered...\n");
                return false;
            }
        } else {
            $this->toOutput(" > \033[1;32mOK\033[0m\n");
            $this->correct++;
        }
        $this->total++;
        if (($this->stopAfter > 0) && ($this->total >= $this->stopAfter)) {
            $this->toOutput(" [!] \033[1;33mTerminating - set limit reached...\n");
            return false;
        }
        return true;
    }

    private function stats() : void {
        // stats
        $correctPercent = $this->total == 0 ? 0 : round(($this->correct / $this->total) * 100, 0);
        $incorrectPercent = $this->total == 0 ? 0 : round(($this->incorrect / $this->total) * 100, 0);
        $this->toOutput("\n\n");
        $this->toOutput("\033[1;33mRESULTS:\033[0m\n");
        $this->toOutput(" [+] Correct runs ..... $this->correct ($correctPercent%)\n");
        $this->toOutput(" [-] Incorrect runs ... $this->incorrect ($incorrectPercent%)\n");
        $this->toOutput(" \033[1mTotal ................ $this->total (100%)\033[0m\n\n");
        // errors
        if ($this->displayErrors && !empty($this->errors)) {
            $this->toOutput("\n\nERRORS:\n");
            foreach ($this->errors as $file => $error) {
                $this->toOutput("[$file]\n\n$error->output\n\n");
            }
        }
    }

    private function toOutput(string $message) : void {
        echo $message;
    }
}