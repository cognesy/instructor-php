<?php
namespace Cognesy\InstructorHub\Data;

use Cognesy\InstructorHub\Utils\Mintlify\NavigationItem;

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
        return static::loadExample($baseDir, $path, $index);
    }

    public function toNavigationItem() : NavigationItem {
        return NavigationItem::fromString('cookbook' . $this->toDocPath());
    }

    public function toDocPath() : string {
        return '/' . $this->tab . '/' . $this->group . '/' . $this->docName;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private static function loadExample(string $baseDir, string $path, int $index = 0) : static {
        [$group, $name] = explode('/', $path, 2);

        $info = ExampleInfo::fromFile($baseDir . $path . '/run.php', $name);

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
            hasTitle: $info->hasTitle(),
            title: $info->title,
            docName: $info->docName,
            content: $info->content,
            directory: $baseDir . $path,
            relativePath: './' . $tab . '/' . $path . '/run.php',
            runPath: $baseDir . $path . '/run.php',
        );
    }
}
