<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Closure;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Keeps typed Message iteration in one place.
 * Formatters compose this instead of duplicating the loop.
 */
final class MessageMapper
{
    /** @var Closure(Message): array */
    private Closure $transform;

    /** @param Closure(Message): array $transform */
    public function __construct(Closure $transform)
    {
        $this->transform = $transform;
    }

    /**
     * Map each Message through the transform, collecting non-empty results.
     * One Message → one native array.
     */
    public function map(Messages $messages): array
    {
        $list = [];
        foreach ($messages->messageList()->all() as $message) {
            $native = ($this->transform)($message);
            if ($native !== []) {
                $list[] = $native;
            }
        }

        return $list;
    }

    /**
     * Map each Message through a transform that may return multiple native items.
     * One Message → zero or more native arrays.
     *
     * @param Messages $messages
     * @param Closure(Message): array[] $transform
     */
    public static function flatMap(Messages $messages, Closure $transform): array
    {
        $list = [];
        foreach ($messages->messageList()->all() as $message) {
            foreach ($transform($message) as $native) {
                if ($native !== []) {
                    $list[] = $native;
                }
            }
        }

        return $list;
    }
}
