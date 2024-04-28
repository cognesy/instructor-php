<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\Example;

class ExampleRepository {
    public string $baseDir = '';

    public function __construct(string $baseDir) {
        $this->baseDir = $baseDir ?: ($this->guessBaseDir() . '/');
    }

    public function forEachExample(callable $callback, string $path = '') : array {
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

    private function getExample(string $path, int $index = 0) : Example {
        [$group, $name] = explode('/', $path, 2);

        $content = $this->getContent($path);
        return new Example(
            index: $index,
            group: $group,
            name: $name,
            hasTitle: $this->hasTitle($content),
            title: $this->getTitle($content),
            content: $content,
            directory: $this->baseDir . $path,
            relativePath: './examples/' . $path . '/run.php',
            runPath: $this->getRunPath($path),
        );
    }

    private function getRunPath(string $path) : string {
        return $this->baseDir . $path . '/run.php';
    }

    private function getContent(string $path) : string {
        $runPath = $this->getRunPath($path);
        return file_get_contents($runPath);
    }

    private function getTitle(string $content) : string {
        $header = $this->findMdH1Line($content);
        return $this->cleanStr($header, 60);
    }

    private function hasTitle(string $content) : bool {
        $title = $this->getTitle($content);
        return ($title !== '');
    }

    private function exampleExists(string $path) : bool {
        $runPath = $this->getRunPath($path);
        return file_exists($runPath);
    }

    private function guessBaseDir() : string {
        // get current directory of this script
        return dirname(__DIR__).'/../examples';
    }

    private function getExampleDirectories() : array {
        $files = $this->getSubdirectories('');
        $directories = [];
        foreach ($files as $key => $file) {
            // check if the file is a directory
            if (!is_dir($this->baseDir . '/' . $file)) {
                continue;
            }
            $directories[] = $this->getSubdirectories($file);
        }
        return array_merge([], ...$directories);
    }

    private function getSubdirectories(string $path) : array {
        $fullPath = $this->baseDir . $path;
        $files = scandir($fullPath);
        $files = array_diff($files, array('.', '..'));
        $directories = [];
        foreach ($files as $fileName) {
            if (is_dir($fullPath . '/' . $fileName)) {
                $directories[] = empty($path) ? $fileName : implode('/', [$path, $fileName]);
            }
        }
        return array_merge([], $directories);
    }

    private function hasSubdirectories(string $path) : bool {
        $runPath = $this->baseDir . $path;
        if (!is_dir($runPath)) {
            return false;
        }
        $directories = $this->getSubdirectories($path);
        return count($directories) > 0;
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
