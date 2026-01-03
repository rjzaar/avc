<?php

namespace Behat\WebdriverClassicExtension;

use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Mink\WebdriverClassicDriver\WebdriverClassicDriver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Behat extension for mink/webdriver-classic-driver.
 */
class WebdriverClassicExtension implements Extension
{
    public function getConfigKey(): string
    {
        return 'webdriver_classic';
    }

    public function initialize(ExtensionManager $extensionManager): void
    {
        /** @var MinkExtension|null $minkExtension */
        $minkExtension = $extensionManager->getExtension('mink');
        if ($minkExtension === null) {
            return;
        }

        $minkExtension->registerDriverFactory(new WebdriverClassicFactory());
    }

    public function configure(ArrayNodeDefinition $builder): void
    {
    }

    public function load(ContainerBuilder $container, array $config): void
    {
    }

    public function process(ContainerBuilder $container): void
    {
    }
}
