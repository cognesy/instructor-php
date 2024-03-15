<?php
namespace Cognesy\InstructorHub\Services;

class Examples {
    public string $baseDir = '';

    public function __construct(string $baseDir) {
        $this->baseDir = $baseDir ?: $this->guessBaseDir();
    }

    public function getBaseDir() : string {
        return $this->baseDir;
    }

    private function guessBaseDir() : string {
        // get current directory of this script
        return dirname(__DIR__).'/../examples';
    }

    public function getExampleDirectories() : array {
        // get all files in the directory
        $files = scandir($this->baseDir);
        // remove . and .. from the list
        $files = array_diff($files, array('.', '..'));
        foreach ($files as $key => $file) {
            // check if the file is a directory
            if (!is_dir($this->baseDir . '/' . $file)) {
                unset($files[$key]);
            }
        }
        return $files;
    }

    public function exampleExists(string $file) : bool {
        return file_exists($this->baseDir . '/' . $file . '/run.php');
    }

    public function forEachFile(callable $callback) : void {
        $directories = $this->getExampleDirectories();
        // loop through the files and select only directories
        foreach ($directories as $file) {
            // check if run.php exists in the directory
            if (!$this->exampleExists($file)) {
                continue;
            }
            if (!$callback($file)) {
                break;
            }
        }
    }

    public function getHeader(string $file) : string {
        $output = file_get_contents($this->baseDir . '/' . $file . '/run.php');
        $lines = explode("\n", $output);
        $header = $lines[0];
        // skip first 2 new lines
        //$header = substr($output, 0, 80);
        // replace new lines with spaces
        $header = str_replace(array("\n", "\r"), array(' ', ''), $header);
        // remove leading and trailing spaces
        $header = trim($header);
        // remove any PHP tags
        $header = str_replace(['<?php', '?>', '#'], '', $header);
        // remove double spaces
        $header = preg_replace('/\s+/', ' ', $header);
        // remove any ANSI codes
        $header = preg_replace('/\e\[[\d;]*m/', '', $header);
        return substr(trim($header), 0, 60);
    }
}
