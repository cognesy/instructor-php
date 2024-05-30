<?php
namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;
use Cognesy\Instructor\Extras\Tasks\TaskData\ObjectDataModel;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ObjectSignature implements Signature, CanProvideSchema
{
    protected object $input;
    protected object $output;
    protected string $description = '';

    public function __construct(object $input, object $output, string $description = null)
    {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->input = $input;
        $this->output = $output;
        $this->input = new ObjectDataModel($input, $fields['inputs']);
        $this->output = new ObjectDataModel($output, $fields['outputs']);
    }

    public function toSchema(): Schema {

    }

    public function toInputSchema(): Schema {
    }

    public function toOutputSchema(): Schema {
    }

    public function input(): DataModel {
    }

    public function output(): DataModel {
    }

    public function description(): string {
    }

    public function withArgs(...$inputs): static {
    }

    public function toSignatureString(): string {
    }

    public function toArray(): array {
    }
}