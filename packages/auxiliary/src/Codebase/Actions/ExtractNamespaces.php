<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Actions;

use Cognesy\Auxiliary\Codebase\Data\CodeNamespace;

class ExtractNamespaces
{
    public function __invoke(array $classes, array $functions): array {
        $anyNamespaces = [];

        // go through all classes and extract namespaces
        /** @var string[] */
        $classNamespaces = [];
        foreach ($classes as $class) {
            $classNamespaces[$class->namespace][] = $class->name;
            $anyNamespaces[$class->namespace] = true;
        }

        // go through all functions and extract namespaces
        /** @var string[] */
        $functionNamespaces = [];
        foreach ($functions as $function) {
            $functionNamespaces[$function->namespace][] = $function->name;
            $anyNamespaces[$class->namespace] = true;
        }

        // find only direct children for each namespace
        $subNamespaces = [];
        foreach (array_keys($anyNamespaces) as $namespace) {
            $subNamespaces[$namespace] = [];
            foreach (array_keys($anyNamespaces) as $subNamespace) {
                if ($namespace === $subNamespace) {
                    continue;
                }
                if (str_starts_with($subNamespace, $namespace)) {
                    // check if namespace is direct child
                    $subNamespaceParts = explode('\\', $subNamespace);
                    $namespaceParts = explode('\\', $namespace);
                    if (count($subNamespaceParts) === count($namespaceParts) + 1) {
                        $subNamespaces[$namespace][] = $subNamespace;
                    }
                }
            }
        }

        $namespaces = [];
        foreach (array_keys($anyNamespaces) as $namespace) {
            $namespaces[$namespace] = new CodeNamespace(
                name: $namespace,
                namespaces: $subNamespaces[$namespace] ?? [],
                classes: $classNamespaces[$namespace] ?? [],
                functions: $functionNamespaces[$namespace] ?? [],
            );
        }

        return $namespaces;
    }
}