<?php

namespace Behat\WebdriverClassicExtension;

use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Driver factory for mink/webdriver-classic-driver.
 */
class WebdriverClassicFactory implements DriverFactory
{
    public function getDriverName(): string
    {
        return 'webdriver_classic';
    }

    public function supportsJavascript(): bool
    {
        return true;
    }

    public function configure(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('browser')->defaultValue('chrome')->end()
                ->scalarNode('wd_host')->defaultValue('http://localhost:4444/wd/hub')->end()
                ->arrayNode('capabilities')
                    ->useAttributeAsKey('name')
                    ->prototype('variable')->end()
                ->end()
            ->end();
    }

    public function buildDriver(array $config): Definition
    {
        // Merge default capabilities with user-provided ones
        $capabilities = $config['capabilities'] ?? [];

        // Accept insecure certificates (for DDEV self-signed certs)
        $capabilities['acceptInsecureCerts'] = true;

        // Add headless mode for Chrome if not specified
        if (!isset($capabilities['goog:chromeOptions'])) {
            $capabilities['goog:chromeOptions'] = [
                'args' => [
                    '--headless=new',
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--window-size=1920,1080',
                    '--ignore-certificate-errors',
                ],
            ];
        }

        return new Definition(
            'Mink\WebdriverClassicDriver\WebdriverClassicDriver',
            [
                $config['browser'],
                $capabilities,
                $config['wd_host'],
            ]
        );
    }
}
