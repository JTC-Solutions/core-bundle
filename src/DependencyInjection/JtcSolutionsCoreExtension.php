<?php
declare(strict_types = 1);

namespace JtcSolutions\Core\DependencyInjection;

use JtcSolutions\Core\ParamResolver\EntityParamResolver;
use JtcSolutions\Core\ParamResolver\UuidQueryParamResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class JtcSolutionsCoreExtension extends Extension
{
    protected const string ALIAS = 'jtc_solutions_core';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // allow disabling uuid resolver
        if (isset($config['enable_uuid_resolver']) && $config['enable_uuid_resolver'] === false) {
            $container->removeDefinition(UuidQueryParamResolver::class);
        }

        // allow disabling entity param resolver
        if (isset($config['enable_entity_resolver']) && $config['enable_entity_resolver'] === false) {
            $container->removeDefinition(EntityParamResolver::class);
        }

        $container->setParameter('jtc_solutions_core.exception_translation_domain', $config['exception_listeners']['translation_domain']);
    }

    public function getAlias(): string
    {
        return static::ALIAS;
    }
}
