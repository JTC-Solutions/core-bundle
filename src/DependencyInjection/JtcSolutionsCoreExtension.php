<?php declare(strict_types=1);

namespace JtcSolutions\Core\DependencyInjection;

use JtcSolutions\Core\Listener\ExceptionListener;
use JtcSolutions\Core\ParamResolver\EntityParamResolver;
use JtcSolutions\Core\ParamResolver\UuidQueryParamResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class JtcSolutionsCoreExtension extends Extension
{
    protected const string ALIAS = 'jtc_solutions_core';

    /**
     * @param array<array<string, mixed>> $configs The configurations defined by the user
     * @param ContainerBuilder $container The container builder
     * @throws \Exception If the configuration is invalid
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Handle UuidQueryParamResolver based on config
        if ($config['param_resolvers']['uuid_resolver']['enable'] === false) {
            $container->removeDefinition(UuidQueryParamResolver::class);
        }

        // Handle EntityParamResolver based on config
        if ($config['param_resolvers']['entity_resolver']['enable'] === false) {
            $container->removeDefinition(EntityParamResolver::class);
        }

        // Handle ExceptionListener based on config
        if ($config['listeners']['exception_listener']['enable'] === false) {
            // Assuming the service ID matches the class name, adjust if necessary
            $container->removeDefinition(ExceptionListener::class);
        } else {
            // Set the translation domain parameter only if the listener is enabled
            $container->setParameter(
                'jtc_solutions_core.exception_translation_domain',
                $config['listeners']['exception_listener']['translation_domain']
            );

            // Optionally, ensure the listener definition exists if it's enabled
            // This might be useful if the service definition is conditional elsewhere
            if ($container->hasDefinition(ExceptionListener::class)) {
                $listenerDefinition = $container->getDefinition(ExceptionListener::class);
                // You could potentially inject the domain directly here if needed,
                // but setting the parameter is usually sufficient if the service uses %parameter_name%
                // $listenerDefinition->setArgument('$translationDomain', $config['listeners']['exception_listener']['translation_domain']);
            }
        }
    }

    public function getAlias(): string
    {
        return static::ALIAS;
    }
}