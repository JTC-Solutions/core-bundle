<?php declare(strict_types = 1);

namespace JtcSolutions\Core\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('jtc_solutions_core');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('param_resolvers')
                    ->children()
                        ->booleanNode('enable_uuid_resolver')
                            ->info('Enable the UuidQueryParameterResolver service, which automatically converts uuid to controller parameter ?')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('enable_entity_resolver')
                            ->info('Enable the EntityParamResolver service, which uses repository find() method instead of doctrine\'s inner methods.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('exception_listeners')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('translation_domain')
                            ->info('The translation domain to use for exception messages.')
                            ->defaultValue('exceptions')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
