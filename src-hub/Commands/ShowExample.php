<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Utils\CliMarkdown;
use Cognesy\InstructorHub\Utils\Color;

class ShowExample extends Command
{
    public string $name = "show";
    public string $description = "Show example";

    public function __construct(
        private ExampleRepository $examples,
    ) {}

    public function execute(array $params = []) {
        $file = $params[0] ?? '';
        if (empty($file)) {
            Cli::outln("Please specify an example to show");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return;
        }
        //$showErrors = ($params[1]=='--debug') ?? false;
        $example = $this->examples->resolveToExample($file);
        if (empty($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return;
        }

        Cli::out("Example: ", [Color::DARK_GRAY]);
        Cli::outln($example->name, [Color::BOLD, Color::WHITE]);
        Cli::outln("---");
        Cli::outln();
        Cli::outln();

        $parser = new CliMarkdown;
        $md = $parser->parse($example->content);
        Cli::outln($md);
        Cli::outln("---");
        Cli::outln();
        Cli::outln("Run this example:", [Color::DARK_YELLOW]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("./hub.sh run {$example->name}", [Color::BOLD, Color::WHITE]);
        Cli::outln();
    }
}
