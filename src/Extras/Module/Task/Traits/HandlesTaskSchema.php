<?php
namespace Cognesy\Instructor\Extras\Module\Task\Traits;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
trait HandlesTaskSchema
{
    public function toInputSchema(): Schema {
        return $this->input->toSchema();
    }

    public function toOutputSchema(): Schema {
        return $this->output->toSchema();
    }

    public function description(): string {
        return $this->description;
    }
}