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
                        ->arrayNode('uuid_resolver')
                            ->children()
                                ->booleanNode('enable')
                                    ->info('Enable the UuidQueryParameterResolver service, which automatically converts uuid to controller parameter ?')
                                    ->defaultTrue()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('entity_resolver')
                            ->children()
                                ->booleanNode('enable')
                                    ->info('Enable the EntityParamResolver service, which uses find() method in repository instead of direct call of Entity Repository')
                                    ->defaultTrue()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('listeners')
                    ->children()
                        ->arrayNode('exception_listener')
                            ->children()
                                ->booleanNode('enable')
                                    ->info('Enable the ExceptionListener service, which converts exceptions to Response objects.')
                                    ->defaultTrue()
                                ->end()
                                ->scalarNode('translation_domain')
                                    ->info('The translation domain to use for exception messages.')
                                    ->defaultValue('exceptions')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('open_api')
                    ->children()
                        ->arrayNode('property_describers')
                            ->children()
                                ->arrayNode('uuid_interface_property_describer')
                                    ->children()
                                        ->booleanNode('enable')
                                        ->info('Enable the UuidInterfacePropertyDescriber service, which adds UuidInterface properties to the OpenAPI schema.')
                                        ->defaultTrue()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
