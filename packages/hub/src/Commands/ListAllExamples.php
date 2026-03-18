<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ListAllExamples extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('list')
            ->setDescription('List all examples')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Filter by tag (repeatable)')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Filter by tags, e.g. "[agents,streaming]" or "agents,streaming"');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $tags = $this->requestedTags($input);
        $examples = $this->examples->getExamplesMatchingTags($tags);

        Cli::outln($this->listingMessage($tags), [Color::BOLD, Color::YELLOW]);

        if ($examples === []) {
            Cli::outln('No examples match the specified tags.', [Color::YELLOW]);
            return Command::SUCCESS;
        }

        foreach ($examples as $example) {
            $idDisplay = !empty($example->id) ? 'x' . $example->id : '-----';
            $nameColor = $example->skip ? Color::DARK_GRAY : Color::GREEN;
            $titleColor = Color::DARK_GRAY;
            $indexColor = $example->skip ? Color::DARK_GRAY : Color::WHITE;
            $idColor = $example->skip ? Color::DARK_GRAY : Color::CYAN;
            $tabColor = $example->skip ? Color::DARK_GRAY : Color::DARK_YELLOW;
            Cli::grid([
                [1, '(', STR_PAD_LEFT, Color::DARK_GRAY],
                [3, $example->index, STR_PAD_LEFT, $indexColor],
                [1, ')', STR_PAD_LEFT, Color::DARK_GRAY],
                [1, '[', STR_PAD_LEFT, Color::DARK_GRAY],
                [5, $idDisplay, STR_PAD_RIGHT, $idColor],
                [1, ']', STR_PAD_LEFT, Color::DARK_GRAY],
                [12, $example->tab, STR_PAD_RIGHT, $tabColor],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [16, $example->group, STR_PAD_LEFT, $tabColor],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [20, $example->name, STR_PAD_RIGHT, $nameColor],
                [2, '-', STR_PAD_LEFT, Color::WHITE],
                [40, $example->title, STR_PAD_RIGHT, $titleColor],
            ]);
            Cli::outln();
        }

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function requestedTags(InputInterface $input) : array {
        $tags = array_merge(
            $this->normalizeTags($input->getOption('tag')),
            $this->parseTagsOption((string) $input->getOption('tags')),
        );

        return $this->normalizeTags($tags);
    }

    /**
     * @param string[] $tags
     * @return string[]
     */
    private function normalizeTags(array $tags) : array {
        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            $value = trim(strtolower($tag));
            if ($value === '') {
                continue;
            }
            if (isset($seen[$value])) {
                continue;
            }
            $normalized[] = $value;
            $seen[$value] = true;
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function parseTagsOption(string $input) : array {
        $value = trim($input);
        if ($value === '') {
            return [];
        }

        $parsed = str_starts_with($value, '[')
            ? Yaml::parse($value, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)
            : explode(',', $value);

        $tags = is_array($parsed) ? $parsed : [$parsed];

        return $this->normalizeTags(array_map(
            fn(mixed $tag): string => is_scalar($tag) || $tag instanceof \Stringable ? (string) $tag : '',
            $tags,
        ));
    }

    /**
     * @param string[] $tags
     */
    private function listingMessage(array $tags) : string {
        if ($tags === []) {
            return 'Listing all examples...';
        }

        return 'Listing examples tagged with: ' . implode(', ', $tags);
    }
}
