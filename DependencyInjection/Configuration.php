<?php

namespace Coshi\Bundle\TranscodeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('coshi_transcode');

        $rootNode
            ->children()
                ->scalarNode('media_class')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('aws_access_key_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('aws_secret_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('aws_s3_videos_bucket')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('aws_transcoder_videos_pipeline_id')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('aws_transcoder_videos_presets')->isRequired()->cannotBeEmpty()
                    ->children()
                        ->scalarNode('iphone4')->end()
                        ->scalarNode('generic')->end()
                        ->scalarNode('iphone')->end()
                    ->end()
                ->end()
                ->arrayNode('media_provider')->isRequired()->cannotBeEmpty()
                    ->children()
                        ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('aws_transcoder_region')->isRequired()->cannotBeEmpty()->end()
            ->end();
        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
