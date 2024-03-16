<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\Example;

class ExampleRepository {
    public string $baseDir = '';

    public function __construct(string $baseDir) {
        $this->baseDir = $baseDir ?: ($this->guessBaseDir() . '/');
    }

    public function forEachExample(callable $callback) : array {
        $directories = $this->getExampleDirectories();
        // loop through the files and select only directories
        $index = 1;
        $list = [];
        foreach ($directories as $file) {
            // check if run.php exists in the directory
            if (!$this->exampleExists($file)) {
                continue;
            }
            $example = $this->getExample($file, $index);
            if (!$callback($example)) {
                break;
            }
            $index++;
            $list[] = $example;
        }
        return $list;
    }

    public function resolveToExample(string $input) : ?Example {
        // handle example provided by index
        $example = (int) ($input ?? '');
        if ($example > 0) {
            $list = $this->getExampleDirectories();
            $index = $example - 1;
            if (isset($list[$index])) {
                return $this->getExample($list[$index], $index);
            }
        }
        if (!$this->exampleExists($input)) {
            return null;
        }
        return $this->getExample($input);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function getExample(string $file, int $index = 0) : Example {
        $content = $this->getContent($file);
        return new Example(
            index: $index,
            name: $file,
            hasTitle: $this->hasTitle($content),
            title: $this->getTitle($content),
            content: $content,
            directory: $this->baseDir . $file,
            relativePath: './examples/' . $file . '/run.php',
            runPath: $this->getRunPath($file),
        );
    }

    private function getRunPath(string $file) : string {
        return $this->baseDir . $file . '/run.php';
    }

    private function getContent(string $file) : string {
        $path = $this->getRunPath($file);
        return file_get_contents($path);
    }

    private function getTitle(string $content) : string {
        $header = $this->findMdH1Line($content);
        return $this->cleanStr($header, 60);
    }

    private function hasTitle(string $content) : bool {
        $title = $this->getTitle($content);
        return ($title !== '');
    }

    private function exampleExists(string $file) : bool {
        $path = $this->getRunPath($file);
        return file_exists($path);
    }

    private function guessBaseDir() : string {
        // get current directory of this script
        return dirname(__DIR__).'/../examples';
    }

    private function getExampleDirectories() : array {
        // get all files in the directory
        $files = scandir($this->baseDir);
        // remove . and .. from the list
        $files = array_diff($files, array('.', '..'));
        $directories = [];
        foreach ($files as $key => $file) {
            // check if the file is a directory
            if (!is_dir($this->baseDir . '/' . $file)) {
                continue;
            }
            $directories[] = $file;
        }
        return $directories;
    }

    private function cleanStr(string $input, int $limit) : string {
        // remove any \n, \r, PHP tags, md hashes
        $output = str_replace(array("\n", "\r", '<?php', '?>', '#'), array(' ', '', '', '', ''), $input);
        // remove leading and trailing spaces
        $output = trim($output);
        // remove double spaces
        $output = preg_replace('/\s+/', ' ', $output);
        // remove any ANSI codes
        $output = preg_replace('/\e\[[\d;]*m/', '', $output);
        return substr(trim($output), 0, $limit);
    }

    private function findMdH1Line(string $input) : string {
        $lines = explode("\n", $input);
        foreach ($lines as $line) {
            if (substr($line, 0, 2) === '# ') {
                return $line;
            }
        }
        return '';
    }
}
