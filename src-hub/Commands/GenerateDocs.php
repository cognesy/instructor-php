<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Command;

class GenerateDocs extends Command
{
    public string $name = "gendocs";
    public string $description = "Generate documentation";

    public function execute(array $params = []) {
        echo "Generating docs\n";
    }
}
