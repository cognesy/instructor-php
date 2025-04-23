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
            'A01_Basics' => ['tab' => 'instructor', 'name' => 'basics', 'title' => 'Instructor - Basics'],
            'A02_Advanced' => ['tab' => 'instructor', 'name' => 'advanced', 'title' => 'Instructor - Advanced'],
            'A03_Troubleshooting' => ['tab' => 'instructor', 'name' => 'troubleshooting', 'title' => 'Instructor - Troubleshooting'],
            'A04_APISupport' => ['tab' => 'instructor', 'name' => 'api_support', 'title' => 'Instructor - LLM API Support'],
            'A05_Extras' => ['tab' => 'instructor', 'name' => 'extras', 'title' => 'Instructor - Extras'],
            'B01_LLM' => ['tab' => 'polyglot', 'name' => 'llm_basics', 'title' => 'Polyglot - LLM Basics'],
            'B02_LLMAdvanced' => ['tab' => 'polyglot', 'name' => 'llm_advanced', 'title' => 'Polyglot - LLM Advanced'],
            'B03_LLMTroubleshooting' => ['tab' => 'polyglot', 'name' => 'llm_troubleshooting', 'title' => 'Polyglot - LLM Troubleshooting'],
            'B04_LLMApiSupport' => ['tab' => 'polyglot', 'name' => 'llm_api_support', 'title' => 'Polyglot - LLM API Support'],
            'B05_LLMExtras' => ['tab' => 'polyglot', 'name' => 'llm_extras', 'title' => 'Polyglot - LLM Extras'],
            'C01_ZeroShot' => ['tab' => 'prompting', 'name' => 'zero_shot', 'title' => 'Prompting - Zero-Shot Prompting'],
            'C02_FewShot' => ['tab' => 'prompting', 'name' => 'few_shot', 'title' => 'Prompting - Few-Shot Prompting'],
            'C03_ThoughtGen' => ['tab' => 'prompting', 'name' => 'thought_gen', 'title' => 'Prompting - Thought Generation'],
            'C04_Ensembling' => ['tab' => 'prompting', 'name' => 'ensembling', 'title' => 'Prompting - Ensembling'],
            'C05_SelfCriticism' => ['tab' => 'prompting', 'name' => 'self_criticism', 'title' => 'Prompting - Self-Criticism'],
            'C06_Decomposition' => ['tab' => 'prompting', 'name' => 'decomposition', 'title' => 'Prompting - Decomposition'],
            'C07_Misc' => ['tab' => 'prompting', 'name' => 'misc', 'title' => 'Prompting - Miscellaneous'],
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
