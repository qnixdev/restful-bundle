<?php declare(strict_types=1);

namespace Qnix\RESTful;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Bundle.
 *
 * @author Mykola Vyhivskyi <qnixdev@gmail.com>
 */
class QnixRESTfulBundle extends AbstractBundle
{
    protected string $extensionAlias = 'qnix_restful';

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // load an XML, PHP or YAML file
        $container->import('../config/services.yaml');

        // the "$config" variable is already merged and processed so you can
        // use it directly to configure the service container (when defining an
        // extension class, you also have to do this merging and processing)
    }
}