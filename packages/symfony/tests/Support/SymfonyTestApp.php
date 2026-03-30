<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Tests\Support;

use Closure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SymfonyTestApp
{
    /** @var array<int,self> */
    private static array $instances = [];

    private bool $isShutdown = false;

    private function __construct(
        private readonly TestKernel $kernel,
        private readonly string $workspaceDir,
    ) {}

    /**
     * @param array<string,mixed> $instructorConfig
     * @param array<string,mixed> $frameworkConfig
     * @param list<Closure(ContainerBuilder):void> $containerConfigurators
     */
    public static function boot(
        array $instructorConfig = [],
        array $frameworkConfig = [],
        array $containerConfigurators = [],
    ): self {
        $workspaceDir = sys_get_temp_dir().'/instructor-symfony-tests/'.bin2hex(random_bytes(10));
        self::ensureDirectory($workspaceDir);

        $kernel = new TestKernel(
            instructorConfig: $instructorConfig,
            frameworkConfig: $frameworkConfig,
            containerConfigurators: $containerConfigurators,
            workspaceDir: $workspaceDir,
        );
        $kernel->boot();

        $app = new self($kernel, $workspaceDir);
        self::$instances[spl_object_id($app)] = $app;

        return $app;
    }

    /**
     * @param array<string,mixed> $instructorConfig
     * @param array<string,mixed> $frameworkConfig
     * @param list<Closure(ContainerBuilder):void> $containerConfigurators
     * @param Closure(self):mixed $callback
     */
    public static function using(
        Closure $callback,
        array $instructorConfig = [],
        array $frameworkConfig = [],
        array $containerConfigurators = [],
    ): mixed {
        $app = self::boot(
            instructorConfig: $instructorConfig,
            frameworkConfig: $frameworkConfig,
            containerConfigurators: $containerConfigurators,
        );

        try {
            return $callback($app);
        } finally {
            $app->shutdown();
        }
    }

    public function container(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }

    public function kernel(): TestKernel
    {
        return $this->kernel;
    }

    public function service(string $id): mixed
    {
        return $this->container()->get($id);
    }

    public function shutdown(): void
    {
        if ($this->isShutdown) {
            return;
        }

        $this->isShutdown = true;
        $this->kernel->shutdown();
        self::restoreHandlers();
        self::removeDirectory($this->workspaceDir);
        unset(self::$instances[spl_object_id($this)]);
    }

    public function __destruct()
    {
        $this->shutdown();
    }

    public static function shutdownAll(): bool
    {
        foreach (self::$instances as $app) {
            $app->shutdown();
        }

        SymfonyTestServiceRegistry::reset();

        return true;
    }

    private static function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }

    private static function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $entryPath = $path.'/'.$entry;

            match (is_dir($entryPath)) {
                true => self::removeDirectory($entryPath),
                false => @unlink($entryPath),
            };
        }

        @rmdir($path);
    }

    private static function restoreHandlers(): void
    {
        restore_exception_handler();
        restore_exception_handler();
    }
}
