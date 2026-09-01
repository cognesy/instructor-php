<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class TellArchitectureRules
{
    /** @return list<string> */
    public static function phpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param list<string> $files
     * @param list<string> $allowedPrefixes
     * @return list<string>
     */
    public static function dependencyViolations(array $files, array $allowedPrefixes): array
    {
        $violations = [];
        foreach ($files as $file) {
            foreach (self::tellDependencies($file) as $dependency) {
                if (!self::startsWithAny($dependency, $allowedPrefixes)) {
                    $violations[] = basename($file) . ': ' . $dependency;
                }
            }
        }
        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public static function frameworkViolations(array $files): array
    {
        $forbidden = [
            'Cognesy\\Cordis\\',
            'Illuminate\\',
            'Psr\\Container\\',
            'Symfony\\Component\\DependencyInjection\\',
        ];
        $violations = [];
        foreach ($files as $file) {
            foreach (self::dependencies($file) as $dependency) {
                if (self::startsWithAny($dependency, $forbidden)) {
                    $violations[] = basename($file) . ': ' . $dependency;
                }
            }
        }
        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public static function capabilitySiblingViolations(array $files): array
    {
        $violations = [];
        foreach ($files as $file) {
            $source = self::source($file);
            $provider = self::capabilityProvider(self::namespace($source));
            if ($provider === null) {
                continue;
            }
            foreach (self::referencesFromSource($source) as $dependency) {
                $dependencyProvider = self::capabilityProvider($dependency);
                if ($dependencyProvider !== null && self::isSiblingProvider($provider, $dependencyProvider)) {
                    $violations[] = basename($file) . ': ' . $dependency;
                }
            }
        }
        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * Reports construction of a concrete Capability class outside Composition.
     * Construction inside the same capability family is a provider-private helper,
     * not selection of a system provider.
     *
     * @param list<string> $files
     * @return list<string>
     */
    public static function providerSelectionViolations(array $files): array
    {
        $violations = [];
        foreach ($files as $file) {
            $source = self::source($file);
            $namespace = self::namespace($source);
            if (str_starts_with($namespace, 'Cognesy\\Tell\\Composition\\')) {
                continue;
            }
            $ownerFamily = self::capabilityFamily($namespace);
            $analysis = self::analyze($source);
            foreach ($analysis['constructions'] as $dependency) {
                $providerFamily = self::capabilityFamily($dependency);
                if ($providerFamily === null || $providerFamily === $ownerFamily) {
                    continue;
                }
                $violations[] = basename($file) . ': new ' . $dependency;
            }
            foreach ($analysis['staticCalls'] as $call) {
                $providerFamily = self::capabilityFamily($call['class']);
                if ($providerFamily === null || $providerFamily === $ownerFamily) {
                    continue;
                }
                $violations[] = basename($file) . ': ' . $call['class'] . '::' . $call['method'];
            }
        }
        $violations = array_values(array_unique($violations));
        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * Reports composition roles and bundled default selection owned by a
     * capability provider. A single provider-private policy/helper remains valid.
     *
     * @param list<string> $files
     * @return list<string>
     */
    public static function providerCompositionViolations(array $files): array
    {
        $violations = [];
        foreach ($files as $file) {
            $source = self::source($file);
            $namespace = self::namespace($source);
            if (self::capabilityProvider($namespace) === null) {
                continue;
            }

            $analysis = self::analyze($source);
            $compositionClasses = array_values(array_filter(
                $analysis['classes'],
                static fn (string $class): bool => self::isCompositionRole(self::shortName($class)),
            ));
            foreach ($compositionClasses as $class) {
                $violations[] = basename($file) . ': defines ' . $class;
            }

            $selections = self::providerDefaultSelections($analysis, self::capabilityFamily($namespace));
            $roles = array_values(array_unique(array_column($selections, 'role')));
            if ($compositionClasses === [] && count($roles) < 2) {
                continue;
            }
            foreach ($selections as $selection) {
                $violations[] = basename($file) . ': ' . $selection['reference'];
            }
        }
        $violations = array_values(array_unique($violations));
        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public static function importSideEffectViolations(array $files): array
    {
        $violations = [];
        foreach ($files as $file) {
            $tokens = token_get_all(self::source($file));
            $braceDepth = 0;
            foreach ($tokens as $index => $token) {
                if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                    $braceDepth++;
                    continue;
                }
                if ($token === '{') {
                    $braceDepth++;
                    continue;
                }
                if ($token === '}') {
                    $braceDepth--;
                    continue;
                }
                if ($braceDepth !== 0 || !is_array($token)) {
                    continue;
                }
                if (in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE, T_ECHO, T_PRINT, T_EXIT], true)) {
                    $violations[] = basename($file) . ':' . $token[2] . ' has top-level execution';
                    break;
                }
                if ($token[0] === T_VARIABLE) {
                    $violations[] = basename($file) . ':' . $token[2] . ' has top-level execution';
                    break;
                }
                if ($token[0] === T_STRING && self::nextSignificantToken($tokens, $index) === '(') {
                    $violations[] = basename($file) . ':' . $token[2] . ' has top-level execution';
                    break;
                }
            }
        }
        sort($violations, SORT_STRING);

        return $violations;
    }

    /** @return list<string> */
    private static function tellDependencies(string $file): array
    {
        return array_values(array_filter(
            self::dependencies($file),
            static fn (string $dependency): bool => str_starts_with($dependency, 'Cognesy\\Tell\\'),
        ));
    }

    /** @return list<string> */
    private static function dependencies(string $file): array
    {
        return self::referencesFromSource(self::source($file));
    }

    /** @return list<string> */
    private static function referencesFromSource(string $source): array
    {
        return self::analyze($source)['references'];
    }

    private static function namespace(string $source): string
    {
        return self::analyze($source)['namespace'];
    }

    /**
     * @return array{
     *     namespace: string,
     *     references: list<string>,
     *     constructions: list<string>,
     *     staticCalls: list<array{class: string, method: string}>,
     *     classes: list<string>
     * }
     */
    private static function analyze(string $source): array
    {
        $tokens = token_get_all($source);
        [$namespace, $imports, $declarationTokens] = self::declarations($tokens);
        $references = array_values($imports);
        $constructions = [];
        $staticCalls = [];
        $classes = [];

        foreach ($tokens as $index => $token) {
            if (isset($declarationTokens[$index]) || !is_array($token)) {
                continue;
            }
            if (self::isQualifiedNameToken($token[0])) {
                $references[] = self::resolveName($token[1], $namespace, $imports);
            }
            if ($token[0] === T_NEW) {
                $nameIndex = self::nextSignificantIndex($tokens, $index);
                if ($nameIndex !== null && self::isNameToken($tokens[$nameIndex])) {
                    $constructions[] = self::resolveName(self::tokenText($tokens[$nameIndex]), $namespace, $imports);
                }
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $nameIndex = self::nextSignificantIndex($tokens, $index);
                if ($nameIndex !== null && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_STRING) {
                    $classes[] = self::qualifyDeclaredName($tokens[$nameIndex][1], $namespace);
                }
                continue;
            }
            if (!self::isNameToken($token)) {
                continue;
            }
            $separatorIndex = self::nextSignificantIndex($tokens, $index);
            if ($separatorIndex === null || self::tokenText($tokens[$separatorIndex]) !== '::') {
                continue;
            }
            $methodIndex = self::nextSignificantIndex($tokens, $separatorIndex);
            if ($methodIndex === null || !is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) {
                continue;
            }
            $method = $tokens[$methodIndex][1];
            if (strtolower($method) === 'class') {
                continue;
            }
            $class = self::resolveName($token[1], $namespace, $imports);
            if ($class !== '') {
                $staticCalls[] = ['class' => $class, 'method' => $method];
            }
        }

        $references = self::normalizedNames([...$references, ...$constructions, ...array_column($staticCalls, 'class')]);

        return [
            'namespace' => $namespace,
            'references' => $references,
            'constructions' => self::normalizedNames($constructions),
            'staticCalls' => self::uniqueStaticCalls($staticCalls),
            'classes' => self::normalizedNames($classes),
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{string, array<string, string>, array<int, true>}
     */
    private static function declarations(array $tokens): array
    {
        $namespace = '';
        $imports = [];
        $ignored = [];
        $braceDepth = 0;
        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $braceDepth++;
                continue;
            }
            if ($token === '}') {
                $braceDepth--;
                continue;
            }
            if (!is_array($token) || $braceDepth !== 0) {
                continue;
            }
            if ($token[0] === T_NAMESPACE) {
                [$declaration, $end] = self::readDeclaration($tokens, $index, true);
                $namespace = trim($declaration, " \\t\\n\\r\\0\\x0B\\\\");
                self::ignoreRange($ignored, $index, $end);
                continue;
            }
            if ($token[0] !== T_USE) {
                continue;
            }
            [$declaration, $end] = self::readDeclaration($tokens, $index, false);
            $imports = [...$imports, ...self::parseImports($declaration)];
            self::ignoreRange($ignored, $index, $end);
        }

        return [$namespace, $imports, $ignored];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{string, int}
     */
    private static function readDeclaration(array $tokens, int $start, bool $namespace): array
    {
        $declaration = '';
        $count = count($tokens);
        for ($index = $start + 1; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === ';' || ($namespace && $token === '{')) {
                return [$declaration, $index];
            }
            $declaration .= self::tokenText($token);
        }

        return [$declaration, $count - 1];
    }

    /** @return array<string, string> */
    private static function parseImports(string $declaration): array
    {
        $declaration = trim($declaration);
        if (preg_match('/^(?:function|const)\\s/i', $declaration) === 1) {
            return [];
        }

        $open = strpos($declaration, '{');
        if ($open === false) {
            return self::parseImportEntries($declaration, '');
        }
        $close = strrpos($declaration, '}');
        if ($close === false || $close < $open) {
            return [];
        }
        $prefix = rtrim(trim(substr($declaration, 0, $open)), '\\');
        $entries = substr($declaration, $open + 1, $close - $open - 1);

        return self::parseImportEntries($entries, $prefix);
    }

    /** @return array<string, string> */
    private static function parseImportEntries(string $entries, string $prefix): array
    {
        $imports = [];
        foreach (explode(',', $entries) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || preg_match('/^(?:function|const)\\s/i', $entry) === 1) {
                continue;
            }
            preg_match('/^(?<name>[^\\s]+)(?:\\s+as\\s+(?<alias>[A-Za-z_][A-Za-z0-9_]*))?$/i', $entry, $match);
            if (!isset($match['name'])) {
                continue;
            }
            $name = ltrim($match['name'], '\\');
            $class = $prefix === '' ? $name : $prefix . '\\' . $name;
            $alias = $match['alias'] ?? self::shortName($class);
            $imports[$alias] = $class;
        }

        return $imports;
    }

    /**
     * @param array<int, true> $ignored
     */
    private static function ignoreRange(array &$ignored, int $start, int $end): void
    {
        for ($index = $start; $index <= $end; $index++) {
            $ignored[$index] = true;
        }
    }

    /** @param array<string, string> $imports */
    private static function resolveName(string $name, string $namespace, array $imports): string
    {
        if (in_array(strtolower($name), ['self', 'static', 'parent'], true)) {
            return '';
        }
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        if (str_starts_with(strtolower($name), 'namespace\\')) {
            return self::qualifyDeclaredName(substr($name, 10), $namespace);
        }

        [$first, $suffix] = array_pad(explode('\\', $name, 2), 2, null);
        if (isset($imports[$first])) {
            return $suffix === null ? $imports[$first] : $imports[$first] . '\\' . $suffix;
        }

        return self::qualifyDeclaredName($name, $namespace);
    }

    private static function qualifyDeclaredName(string $name, string $namespace): string
    {
        $name = ltrim($name, '\\');

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /** @param array{constructions: list<string>, staticCalls: list<array{class: string, method: string}>} $analysis
     * @return list<array{role: string, reference: string}>
     */
    private static function providerDefaultSelections(array $analysis, ?string $ownerFamily): array
    {
        $selections = [];
        foreach ($analysis['constructions'] as $class) {
            if (self::capabilityFamily($class) !== $ownerFamily) {
                continue;
            }
            $role = self::defaultSelectionRole(self::shortName($class));
            if ($role !== null) {
                $selections[] = ['role' => $role, 'reference' => 'new ' . $class];
            }
        }
        foreach ($analysis['staticCalls'] as $call) {
            if (self::capabilityFamily($call['class']) !== $ownerFamily) {
                continue;
            }
            $role = self::staticSelectionRole(self::shortName($call['class']));
            if ($role !== null) {
                $selections[] = [
                    'role' => $role,
                    'reference' => $call['class'] . '::' . $call['method'],
                ];
            }
        }

        return $selections;
    }

    private static function defaultSelectionRole(string $class): ?string
    {
        return match (true) {
            self::isCompositionRole($class) => 'host',
            str_ends_with($class, 'Policy') => 'policy',
            str_starts_with($class, 'Null') && str_ends_with($class, 'Observer') => 'observer',
            default => null,
        };
    }

    private static function staticSelectionRole(string $class): ?string
    {
        return match (true) {
            str_ends_with($class, 'Approvals') => 'approvals',
            str_ends_with($class, 'Observers') => 'observer',
            default => null,
        };
    }

    private static function isCompositionRole(string $class): bool
    {
        return str_ends_with($class, 'Host')
            || str_ends_with($class, 'HostBuilder')
            || str_ends_with($class, 'Profile');
    }

    /** @param list<string> $names
     * @return list<string>
     */
    private static function normalizedNames(array $names): array
    {
        $names = array_values(array_unique(array_filter(
            $names,
            static fn (string $name): bool => $name !== '',
        )));
        sort($names, SORT_STRING);

        return $names;
    }

    /** @param list<array{class: string, method: string}> $calls
     * @return list<array{class: string, method: string}>
     */
    private static function uniqueStaticCalls(array $calls): array
    {
        $unique = [];
        foreach ($calls as $call) {
            $unique[$call['class'] . '::' . $call['method']] = $call;
        }
        ksort($unique, SORT_STRING);

        return array_values($unique);
    }

    private static function capabilityFamily(string $namespace): ?string
    {
        return self::capabilityProvider($namespace)['family'] ?? null;
    }

    /** @return array{family: string, strategy: ?string}|null */
    private static function capabilityProvider(string $namespace): ?array
    {
        preg_match('/^Cognesy\\\\Tell\\\\Capability\\\\([^\\\\]+)(?:\\\\([^\\\\]+))?/', $namespace, $match);

        if (!isset($match[1])) {
            return null;
        }

        return ['family' => $match[1], 'strategy' => $match[2] ?? null];
    }

    /**
     * @param array{family: string, strategy: ?string} $owner
     * @param array{family: string, strategy: ?string} $dependency
     */
    private static function isSiblingProvider(array $owner, array $dependency): bool
    {
        if ($owner['family'] !== $dependency['family']) {
            return true;
        }

        return $owner['strategy'] !== null
            && $dependency['strategy'] !== null
            && $owner['strategy'] !== $dependency['strategy'];
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /** @param list<string> $prefixes */
    private static function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $matches = str_ends_with($prefix, '\\')
                ? str_starts_with($value, $prefix)
                : $value === $prefix;
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private static function nextSignificantIndex(array $tokens, int $index): ?int
    {
        $count = count($tokens);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $cursor;
        }

        return null;
    }

    /** @param array{int, string, int}|string $token */
    private static function isNameToken(array|string $token): bool
    {
        return is_array($token) && ($token[0] === T_STRING || self::isQualifiedNameToken($token[0]));
    }

    private static function isQualifiedNameToken(int $token): bool
    {
        return in_array($token, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true);
    }

    /** @param array{int, string, int}|string $token */
    private static function tokenText(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    /** @param array<int, array{int, string, int}|string> $tokens */
    private static function nextSignificantToken(array $tokens, int $index): array|string|null
    {
        $count = count($tokens);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private static function source(string $file): string
    {
        $source = file_get_contents($file);

        return is_string($source) ? $source : '';
    }
}
