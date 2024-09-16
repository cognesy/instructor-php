<?php
namespace Cognesy\InstructorHub\Data;

use Cognesy\Instructor\Utils\Str;
use Cognesy\InstructorHub\Utils\Mintlify\NavigationItem;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class Example
{
    public function __construct(
        public int    $index = 0,
        public string $tab = '',
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
        return '/' . $this->tab . '/' . $this->group . '/' . $this->docName;
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
            'A01_Basics' => ['tab' => 'examples', 'name' => 'basics', 'title' => 'Basics'],
            'A02_Advanced' => ['tab' => 'examples', 'name' => 'advanced', 'title' => 'Advanced'],
            'A03_Troubleshooting' => ['tab' => 'examples', 'name' => 'troubleshooting', 'title' => 'Troubleshooting'],
            'A04_APISupport' => ['tab' => 'examples', 'name' => 'api_support', 'title' => 'LLM API Support'],
            'A05_Extras' => ['tab' => 'examples', 'name' => 'extras', 'title' => 'Extras'],
            'B01_ZeroShot' => ['tab' => 'prompting', 'name' => 'zero_shot', 'title' => 'Zero-Shot Prompting'],
            'B02_FewShot' => ['tab' => 'prompting', 'name' => 'few_shot', 'title' => 'Few-Shot Prompting'],
            'B03_ThoughtGen' => ['tab' => 'prompting', 'name' => 'thought_gen', 'title' => 'Thought Generation'],
            'B04_Ensembling' => ['tab' => 'prompting', 'name' => 'ensembling', 'title' => 'Ensembling'],
            'B05_SelfCriticism' => ['tab' => 'prompting', 'name' => 'self_criticism', 'title' => 'Self-Criticism'],
            'B06_Decomposition' => ['tab' => 'prompting', 'name' => 'decomposition', 'title' => 'Decomposition'],
            'B07_Misc' => ['tab' => 'prompting', 'name' => 'misc', 'title' => 'Miscellaneous'],
        ];

        $tab = $mapping[$group]['tab'];
        return new Example(
            index: $index,
            tab: $tab,
            group: $mapping[$group]['name'],
            groupTitle: $mapping[$group]['title'],
            name: $name,
            hasTitle: $hasTitle,
            title: $title,
            docName: $docName,
            content: $content,
            directory: $baseDir . $path,
            relativePath: './' . $tab . '/' . $path . '/run.php',
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
