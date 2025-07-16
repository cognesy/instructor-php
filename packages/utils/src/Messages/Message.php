<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages;

use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Utils\Messages\Traits\Message\HandlesAccess;
use Cognesy\Utils\Messages\Traits\Message\HandlesCreation;
use Cognesy\Utils\Messages\Traits\Message\HandlesMutation;
use Cognesy\Utils\Messages\Traits\Message\HandlesTransformation;

/**
 * Represents a message entity with role, content, and metadata properties.
 *
 * This class provides functionality for creating and managing a message,
 * where the role determines the message's purpose or origin, the content
 * holds the message data, and the metadata contains additional contextual
 * information.
 *
 * It supports complex message content fields like images or audio, and
 * multipart text content, so it can represent a wide range of language
 * model APIs across various LLM providers.
 *
 * Metadata can be used to store arbitrary values needed by an application,
 * such as sources, internal reasoning traces. They are not explicitly rendered
 * to a message content sent to a language model.
 *
 * Each chat message is uniquely identified by an ID, which is generated
 * in the constructor.
 *
 */
class Message {
    use HandlesCreation;
    use HandlesMutation;
    use HandlesAccess;
    use HandlesTransformation;

    public const DEFAULT_ROLE = 'user';

    protected string $role;
    protected string $name;
    protected Content $content;
    protected array $metadata = [];

    /**
     * @param string $role
     * @param string|array<string|array> $content
     */
    public function __construct(
        string|MessageRole $role = '',
        string|array|Content|null $content = null,
        string $name = '',
        array $metadata = [],
    ) {
        $this->role = match(true) {
            $role instanceof MessageRole => $role->value,
            ($role === '') => self::DEFAULT_ROLE,
            default => $role,
        };
        $this->name = $name;
        $this->content = Content::fromAny($content);
        $this->metadata = $metadata;
    }

    /**
     * Checks if given array is OpenAI formatted message
     * @param array $message
     * @return bool
     */
    public static function isMessage(array $message): bool {
        return isset($message['role']) && isset($message['content']);
    }

    /**
     * Checks if given array is OpenAI array of formatted messages
     * @param array $messages
     * @return bool
     */
    public static function isMessages(array $messages): bool {
        return isset($messages[0]['role']) && isset($messages[0]['content']);
    }
}
