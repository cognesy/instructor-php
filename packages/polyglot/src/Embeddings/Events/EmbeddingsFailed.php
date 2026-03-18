<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Events;

use Psr\Log\LogLevel;

class EmbeddingsFailed extends EmbeddingsEvent
{
    public string $logLevel = LogLevel::WARNING;
}

