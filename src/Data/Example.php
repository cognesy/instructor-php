<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Contracts\CanProvideJson;
use Cognesy\Instructor\Contracts\CanProvideMessages;
use DateTimeImmutable;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Example implements CanProvideMessages, CanProvideJson, JsonSerializable
{
    use Traits\Example\HandlesAccess;
    use Traits\Example\HandlesCreation;
    use Traits\Example\HandlesConversion;

    public readonly string $uid;
    public readonly DateTimeImmutable $createdAt;
    private mixed $input;
    private mixed $output;

    public string $template = <<<TEMPLATE
        EXAMPLE INPUT:
        <|input|>
        
        EXAMPLE OUTPUT:
        ```json
        <|output|>
        ```
        TEMPLATE;

    public function __construct(
        mixed $input,
        mixed $output,
        string $template = '',
        string $uid = null,
        DateTimeImmutable $createdAt = null,
    ) {
        $this->uid = $uid ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->template = $template ?: $this->template;
        $this->input = $input;
        $this->output = $output;
    }
}
