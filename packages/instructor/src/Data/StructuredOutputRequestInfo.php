<?php
namespace Cognesy\Instructor\Data;

class StructuredOutputRequestInfo
{
    use Traits\StructuredOutputRequestInfo\HandlesAccess;
    use Traits\StructuredOutputRequestInfo\HandlesMutation;
    use Traits\StructuredOutputRequestInfo\HandlesCreation;
    use Traits\StructuredOutputRequestInfo\HandlesSerialization;

    protected string|array $messages = [];
    protected string|array|object $input = [];
    protected string|array|object $responseModel = [];
    protected string $model = '';
    protected string $system = '';
    protected string $prompt = '';
    protected array $options = [];
    /** @var Example[] */
    protected array $examples = [];

    protected ?StructuredOutputConfig $config = null;
    protected array $cachedContext = [];
}
