<?php

namespace Cognesy\Evals\ComplexExtraction;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Instructor;

class ExtractProjectEvents
{
    public function __construct(
        readonly public string $report,
        readonly public array $examples,
        readonly public string $connection,
        readonly public bool $withStreaming = false,
    ) {}

    public function __invoke() : Sequence {
        $instructor = (new Instructor)->withConnection($this->connection);
        $events = $instructor->respond(
            messages: $this->report,
            responseModel: Sequence::of(ProjectEvent::class),
            prompt: 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>',
            examples: $this->examples,
            options: [
                'max_tokens' => 4096,
                'stream' => $this->withStreaming,
            ],
            mode: Mode::Json,
        );
        return $events;
    }
}