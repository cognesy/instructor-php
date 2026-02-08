<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Hooks\Matchers;

use Cognesy\Addons\Agent\Hooks\Contracts\HookContext;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;

/**
 * Matcher that matches tool contexts by tool name pattern.
 *
 * Supports three pattern types:
 * - Exact match: 'bash' matches only 'bash'
 * - Wildcard: 'read_*' matches 'read_file', 'read_stdin', etc.
 * - Regex: '/^write_.+/' matches 'write_file', 'write_data', etc.
 *
 * Only matches ToolHookContext instances. Returns false for other context types.
 *
 * @example
 * // Match specific tool
 * $matcher = new ToolNameMatcher('bash');
 *
 * // Match all tools starting with 'read_'
 * $matcher = new ToolNameMatcher('read_*');
 *
 * // Match all tools ending with '_file'
 * $matcher = new ToolNameMatcher('*_file');
 *
 * // Match all tools
 * $matcher = new ToolNameMatcher('*');
 *
 * // Use regex for complex patterns
 * $matcher = new ToolNameMatcher('/^(read|write)_.+$/');
 */
final readonly class ToolNameMatcher implements HookMatcher
{
    public function __construct(
        private string $pattern,
    ) {}

    #[\Override]
    public function matches(HookContext $context): bool
    {
        // Only match ToolHookContext
        if (!$context instanceof ToolHookContext) {
            return false;
        }

        $name = $context->toolCall()->name();

        // Match all
        if ($this->pattern === '*') {
            return true;
        }

        // Exact match
        if ($this->pattern === $name) {
            return true;
        }

        // Regex pattern (starts with /)
        if (str_starts_with($this->pattern, '/')) {
            return (bool) preg_match($this->pattern, $name);
        }

        // Wildcard pattern (contains *)
        if (str_contains($this->pattern, '*')) {
            return $this->matchWildcard($name);
        }

        return false;
    }

    /**
     * Match using simple wildcard pattern.
     */
    private function matchWildcard(string $name): bool
    {
        // Convert wildcard pattern to regex
        // Escape regex special chars except *, then replace * with .*
        $escaped = preg_quote($this->pattern, '/');
        $regex = '/^' . str_replace('\\*', '.*', $escaped) . '$/';

        return (bool) preg_match($regex, $name);
    }
}
