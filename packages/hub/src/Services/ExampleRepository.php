<?php declare(strict_types=1);
namespace Cognesy\InstructorHub\Services;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Config\ExampleGrouping;
use Cognesy\InstructorHub\Config\ExampleSource;
use Cognesy\InstructorHub\Config\ExampleSources;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExampleGroupAssignment;
use Cognesy\InstructorHub\Data\ExampleGroup;
use Cognesy\InstructorHub\Data\ExampleLocation;

class ExampleRepository {
    private ExampleSources $sources;
    private ExampleGrouping $grouping;

    public function __construct(?ExampleSources $sources = null, ?ExampleGrouping $grouping = null) {
        $this->sources = $sources ?? ExampleSources::legacy($this->guessBaseDir());
        $this->grouping = $grouping ?? ExampleGrouping::empty();
    }

    /** @return ExampleGroup[] */
    public function getExampleGroups() : array {
        return $this->getExamplesInGroups();
    }

    /**
     * @param callable(Example): bool $callback
     * @return array<Example>
     */
    public function forEachExample(callable $callback, string $path = '') : array {
        $locations = $this->getExampleLocations();
        $index = 1;
        $list = [];
        foreach ($locations as $location) {
            $example = $this->getExample($location, $index);
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
        $example = (int) $input;
        if ($example > 0) {
            $locations = $this->getExampleLocations();
            $offset = $example - 1;
            if (isset($locations[$offset])) {
                return $this->getExample($locations[$offset], $offset);
            }
        }
        $location = $this->findExampleLocation($input);
        if ($location === null) {
            return null;
        }
        return $this->getExample($location);
    }

    /** @return array<Example> */
    public function getAllExamples() : array {
        return $this->forEachExample(fn($example) => true);
    }

    public function getCanonicalIndex(Example $example) : int {
        $allExamples = $this->getAllExamples();
        foreach ($allExamples as $index => $ex) {
            if ($ex->relativePath === $example->relativePath) {
                return $index;
            }
        }
        throw new \RuntimeException("Example not found in repository: {$example->relativePath}");
    }

    public function getExampleByCanonicalIndex(int $index) : ?Example {
        $allExamples = $this->getAllExamples();
        return $allExamples[$index] ?? null;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    /** @return array<string, ExampleGroup> */
    private function getExamplesInGroups() : array {
        $examples = $this->forEachExample(fn($example) => true);
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

    private function getExample(ExampleLocation $location, int $index = 0) : Example {
        $assignment = $this->groupAssignment($location);
        return Example::fromFile($location->source->baseDir, $location->path, $index, $assignment);
    }

    private function getRunPath(ExampleSource $source, string $path) : string {
        return $source->baseDir . $path . '/run.php';
    }

    /** @phpstan-ignore-next-line */
    private function getContent(ExampleSource $source, string $path) : string {
        $runPath = $this->getRunPath($source, $path);
        $content = file_get_contents($runPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$runPath}");
        }
        return $content;
    }

    private function getTitle(string $content) : string {
        $header = $this->findMdH1Line($content);
        return $this->cleanStr($header, 60);
    }

    private function exampleExists(ExampleSource $source, string $path) : bool {
        $runPath = $this->getRunPath($source, $path);
        return file_exists($runPath);
    }

    private function guessBaseDir() : string {
        // get current directory of this script
        return BasePath::get('examples');
    }

    /**
     * @return ExampleLocation[]
     */
    private function getExampleLocations() : array {
        $locations = [];
        $seen = [];
        foreach ($this->sources as $source) {
            $directories = $this->getExampleDirectories($source);
            foreach ($directories as $path) {
                if (!$this->exampleExists($source, $path)) {
                    continue;
                }
                if (isset($seen[$path])) {
                    continue;
                }
                $locations[] = new ExampleLocation($source, $path);
                $seen[$path] = true;
            }
        }
        return $this->grouping->sortLocations($locations);
    }

    private function getExampleDirectories(ExampleSource $source) : array {
        $files = $this->getSubdirectories($source->baseDir, '');
        $directories = [];
        foreach ($files as $file) {
            if (!is_dir($source->baseDir . $file)) {
                continue;
            }
            $directories[] = $this->getSubdirectories($source->baseDir, $file);
        }
        return array_merge([], ...$directories);
    }

    private function findExampleLocation(string $path) : ?ExampleLocation {
        foreach ($this->sources as $source) {
            if ($this->exampleExists($source, $path)) {
                return new ExampleLocation($source, $path);
            }
        }
        return null;
    }

    private function getSubdirectories(string $baseDir, string $path) : array {
        $fullPath = $baseDir . $path;
        if (!is_dir($fullPath)) {
            return [];
        }
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

    private function groupAssignment(ExampleLocation $location): ?ExampleGroupAssignment
    {
        if ($this->grouping->isEmpty()) {
            return null;
        }

        return $this->grouping->assignmentFor($location);
    }

    /** @phpstan-ignore-next-line */
    private function hasSubdirectories(ExampleSource $source, string $path) : bool {
        $runPath = $source->baseDir . $path;
        if (!is_dir($runPath)) {
            return false;
        }
        $directories = $this->getSubdirectories($source->baseDir, $path);
        return count($directories) > 0;
    }

    // DEPRECATED /////////////////////////////////////////////////////////////////////////

    private function cleanStr(string $input, int $limit) : string {
        // remove any \n, \r, PHP tags, md hashes
        $output = str_replace(["\n", "\r", '<?php', '?>', '#'], [' ', '', '', '', ''], $input);
        // remove leading and trailing spaces
        $output = trim($output);
        // remove double spaces
        $output = preg_replace('/\s+/', ' ', $output) ?? $output;
        // remove any ANSI codes
        $output = preg_replace('/\e\[[\d;]*m/', '', $output) ?? $output;
        return substr(trim($output), 0, $limit);
    }

    /** @phpstan-ignore-next-line */
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

}
