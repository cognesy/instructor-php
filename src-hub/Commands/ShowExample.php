<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Examples;

class ShowExample extends Command
{
    public string $name = "show";
    public string $description = "Show example";

    public function __construct(
        private Examples $examples,
    ) {}

    public function execute(array $params = []) {
        echo "Viewing example\n";
    }
}