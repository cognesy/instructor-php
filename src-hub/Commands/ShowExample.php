<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Examples;
use Cognesy\InstructorHub\Utils\CliMarkdown;
use Cognesy\InstructorHub\Utils\Color;

class ShowExample extends Command
{
    public string $name = "show";
    public string $description = "Show example";

    public function __construct(
        private Examples $examples,
    ) {}

    public function execute(array $params = []) {
        $file = $params[0] ?? '';
        //$showErrors = ($params[1]=='--debug') ?? false;
        $file = $this->examples->exampleName($file);
        if (empty($file)) {
            Cli::outln("Please specify an example to show");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return;
        }
        if (!$this->examples->exampleExists($file)) {
            Cli::outln("Example not found", [Color::RED]);
            return;
        }
        Cli::out("Example: ", [Color::DARK_GRAY]);
        Cli::outln($file, [Color::BOLD, Color::WHITE]);
        Cli::outln("---");
        Cli::outln();
        Cli::outln();
        $content = $this->examples->getContent($file);
        $parser = new CliMarkdown;
        $md = $parser->parse($content);
        Cli::outln($md);
        Cli::outln("---");
        Cli::outln();
        Cli::outln("Run this example:", [Color::DARK_YELLOW]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("./hub.sh run {$params[0]}", [Color::BOLD, Color::WHITE]);
        Cli::outln();
    }
}