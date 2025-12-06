<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for Instructor Logging Bundle
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('instructor_logging');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                ->enumNode('preset')
                    ->values(['default', 'production', 'custom'])
                    ->defaultValue('default')
                ->end()
                ->arrayNode('config')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('channel')
                            ->defaultValue('instructor')
                        ->end()
                        ->enumNode('level')
                            ->values(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])
                            ->defaultValue('debug')
                        ->end()
                        ->arrayNode('exclude_events')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('include_events')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('templates')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}