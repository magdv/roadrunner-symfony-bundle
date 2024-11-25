<?php

namespace FluffyDiscord\RoadRunnerBundle\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder("fluffy_discord_road_runner");

        $builder->getRootNode()
            ->children()
                ->scalarNode("rr_config_path")->defaultValue(".rr.yaml")->end()
                ->arrayNode("kv")
                    ->children()
                        ->booleanNode("auto_register")->defaultTrue()->end()
                        ->scalarNode("serializer")->defaultNull()->end()
                        ->scalarNode("keypair_path")->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode("http")
                    ->children()
                        ->booleanNode("lazy_boot")->defaultFalse()->end()
                        ->booleanNode("early_router_initialization")->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode("centrifugo")
                    ->children()
                        ->booleanNode("lazy_boot")->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode("temporal")
                    ->children()
                        ->scalarNode("taskQueue")->defaultValue('taskQueue')->end()
                    ->end()
                    ->children()
                        ->arrayNode("workflow")
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode("activity")
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
