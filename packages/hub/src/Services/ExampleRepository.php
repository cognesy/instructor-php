<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExampleGroup;
use Cognesy\Utils\Config\BasePath;

class ExampleRepository {
    public string $baseDir = '';

    public function __construct(string $baseDir) {
        $this->baseDir = $this->withEndingSlash($baseDir ?: ($this->guessBaseDir() . '/'));
    }

    /** @return ExampleGroup[] */
    public function getExampleGroups() : array {
        return $this->getExamplesInGroups();
    }

    /** @return array<Example> */
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

    public function argToExample(string $input) : ?Example {
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

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    /** @return array<string, array<Example> */
    private function getExamplesInGroups() : array {
        $examples = $this->forEachExample(fn($example) => $example);
        $groups = [];
        foreach ($examples as $example) {
            $group = $example->group;
            if (!isset($groups[$group])) {
                $groups[$group] = new ExampleGroup($example->group, $example->groupTitle, []);
            }
            $groups[$group]->addExample($example);
        }
        return $groups;
    }

    private function getExample(string $path, int $index = 0) : Example {
        return Example::fromFile($this->baseDir, $path, $index);
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

    private function exampleExists(string $path) : bool {
        $runPath = $this->getRunPath($path);
        return file_exists($runPath);
    }

    private function guessBaseDir() : string {
        // get current directory of this script
        return BasePath::get('examples');
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
        $files = scandir($fullPath) ?: [];
        $files = array_diff($files, ['.', '..']);
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

    // DEPRECATED /////////////////////////////////////////////////////////////////////////

    private function cleanStr(string $input, int $limit) : string {
        // remove any \n, \r, PHP tags, md hashes
        $output = str_replace(["\n", "\r", '<?php', '?>', '#'], [' ', '', '', '', ''], $input);
        // remove leading and trailing spaces
        $output = trim($output);
        // remove double spaces
        $output = preg_replace('/\s+/', ' ', $output);
        // remove any ANSI codes
        $output = preg_replace('/\e\[[\d;]*m/', '', $output);
        return substr(trim($output), 0, $limit);
    }

    private function hasTitle(string $content) : bool {
        $title = $this->getTitle($content);
        return ($title !== '');
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

    private function withEndingSlash(string $path) : string {
        return rtrim($path, '/') . '/';
    }
}
