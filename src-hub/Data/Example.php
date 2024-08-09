<?php
namespace Cognesy\InstructorHub\Data;

use Cognesy\Instructor\Utils\Str;
use Cognesy\InstructorHub\Utils\Mintlify\NavigationItem;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class Example
{
    public function __construct(
        public int    $index = 0,
        public string $group = '',
        public string $groupTitle = '',
        public string $name = '',
        public bool   $hasTitle = false,
        public string $title = '',
        public string $docName = '',
        public string $content = '',
        public string $directory = '',
        public string $relativePath = '',
        public string $runPath = '',
    ) {}

    public static function fromFile(string $baseDir, string $path, int $index = 0) : static {
        return (new static)->loadExample($baseDir, $path, $index);
    }

    public function toNavigationItem() : NavigationItem {
        return NavigationItem::fromString('cookbook' . $this->toDocPath());
    }

    public function toDocPath() : string {
        return '/examples/' . $this->group . '/' . $this->docName;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private function loadExample(string $baseDir, string $path, int $index = 0) : static {
        [$group, $name] = explode('/', $path, 2);

        $document = YamlFrontMatter::parseFile($baseDir . $path . '/run.php');
        $content = $document->body();
        $title = $document->matter('title') ?: $this->getTitle($content);
        $docName = $document->matter('docname') ?: Str::snake($name);
        $hasTitle = !empty($title);
        $mapping = [
            '01_Basics' => ['name' => 'basics', 'title' => 'Basics'],
            '02_Advanced' => ['name' => 'advanced', 'title' => 'Advanced'],
            '03_Techniques' => ['name' => 'techniques', 'title' => 'Techniques'],
            '04_Troubleshooting' => ['name' => 'troubleshooting', 'title' => 'Troubleshooting'],
            '05_APISupport' => ['name' => 'api_support', 'title' => 'LLM API Support']
        ];

        return new Example(
            index: $index,
            group: $mapping[$group]['name'],
            groupTitle: $mapping[$group]['title'],
            name: $name,
            hasTitle: $hasTitle,
            title: $title,
            docName: $docName,
            content: $content,
            directory: $baseDir . $path,
            relativePath: './examples/' . $path . '/run.php',
            runPath: $baseDir . $path . '/run.php',
        );
    }

    private function getTitle(string $content) : string {
        $header = $this->findMdH1Line($content);
        return $this->cleanStr($header, 60);
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
