<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('instructor');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->variableNode('connections')
            ->defaultValue([])
            ->end()
            ->variableNode('embeddings')
            ->defaultValue([])
            ->end()
            ->variableNode('extraction')
            ->defaultValue([])
            ->end()
            ->variableNode('http')
            ->defaultValue([])
            ->end()
            ->variableNode('events')
            ->defaultValue([])
            ->end()
            ->variableNode('agent_ctrl')
            ->defaultValue([])
            ->end()
            ->variableNode('agents')
            ->defaultValue([])
            ->end()
            ->variableNode('sessions')
            ->defaultValue([])
            ->end()
            ->variableNode('telemetry')
            ->defaultValue([])
            ->end()
            ->variableNode('logging')
            ->defaultValue([])
            ->end()
            ->variableNode('testing')
            ->defaultValue([])
            ->end()
            ->variableNode('delivery')
            ->defaultValue([])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
