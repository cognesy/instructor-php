<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

final class Workdir
{
    public static function create(ExecutionPolicy $policy, string $prefix = 'sandbox-'): string {
        $base = rtrim($policy->baseDir(), '/');
        $real = realpath($base) ?: '';
        if ($real === '' || !is_dir($real) || !is_writable($real)) {
            throw new \RuntimeException('Base directory is invalid or not writable');
        }
        $um = umask(0o077);
        $i = 0;
        while ($i < 5) {
            $id = bin2hex(random_bytes(12));
            $dir = $base . '/' . $prefix . $id;
            if (mkdir($dir, 0o700, true) || is_dir($dir)) {
                umask($um);
                return $dir;
            }
            $i++;
        }
        umask($um);
        throw new \RuntimeException('Failed to create working directory');
    }

    public static function remove(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            if (is_dir($p)) {
                self::remove($p);
                continue;
            }
            @unlink($p);
        }
        @rmdir($dir);
    }
}

