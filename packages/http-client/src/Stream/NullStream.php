<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use EmptyIterator;
use Traversable;

/**
 * NullStream: completed, non-emitting stream.
 */
final class NullStream implements StreamInterface
{
    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    #[\Override]
    public function getIterator(): Traversable {
        return new EmptyIterator();
    }

    #[\Override]
    public function isCompleted(): bool {
        return true;
    }
}

