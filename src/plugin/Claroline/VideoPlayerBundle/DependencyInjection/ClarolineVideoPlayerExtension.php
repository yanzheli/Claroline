<?php

namespace Claroline\VideoPlayerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Claroline\CoreBundle\Library\Plugin\PlayerDependencyInjection;

class ClarolineVideoPlayerExtension extends PlayerDependencyInjection
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $locator = new FileLocator(__DIR__ . '/../Resources/config/services');
        $loader = new YamlFileLoader($container, $locator);
        $loader->load('services.yml');
        
        parent::setUp($container);
    }
}