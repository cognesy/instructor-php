<?php declare(strict_types=1);

namespace Cognesy\Utils\Context\Psr;

use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Exceptions\MissingServiceException;
use Cognesy\Utils\Context\Key;
use Cognesy\Utils\Context\Psr\Exceptions\ContainerError;
use Cognesy\Utils\Context\Psr\Exceptions\NotFound;
use Psr\Container\ContainerInterface;

/**
 * Read-only PSR-11 view over Context.
 *
 * For qualified bindings, provide a map of key-id => expected class-string type.
 */
final class ContextContainer implements ContainerInterface
{
    /** @var array<string, class-string> */
    private array $keyTypes;

    /**
     * @param array<string, class-string> $keyTypes
     */
    public function __construct(private Context $ctx, array $keyTypes = []) {
        $this->keyTypes = $keyTypes;
    }

    #[\Override]
    public function get(string $id): mixed {
        // Prefer class-string bindings
        if (class_exists($id) || interface_exists($id)) {
            try {
                return $this->ctx->get($id);
            } catch (MissingServiceException $e) {
                throw new NotFound($e->getMessage());
            } catch (\Throwable $e) {
                throw new ContainerError($e->getMessage(), previous: $e);
            }
        }

        // Qualified binding by key id
        if (isset($this->keyTypes[$id])) {
            $key = Key::of($id, $this->keyTypes[$id]);
            try {
                return $this->ctx->getKey($key);
            } catch (MissingServiceException $e) {
                throw new NotFound($e->getMessage());
            } catch (\Throwable $e) {
                throw new ContainerError($e->getMessage(), previous: $e);
            }
        }

        throw new NotFound("Service not found: {$id}");
    }

    #[\Override]
    public function has(string $id): bool {
        if (class_exists($id) || interface_exists($id)) {
            return $this->ctx->has($id);
        }
        if (isset($this->keyTypes[$id])) {
            try {
                $this->ctx->getKey(Key::of($id, $this->keyTypes[$id]));
                return true;
            } catch (MissingServiceException) {
                return false;
            }
        }
        return false;
    }
}

