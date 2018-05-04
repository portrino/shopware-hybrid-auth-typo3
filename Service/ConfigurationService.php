<?php
namespace Port1HybridAuthTypo3\Service;

/**
 * Copyright (C) portrino GmbH - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by (c) Axel Boeswetter <boeswetter@portrino.de>, portrino GmbH
 */
use Port1HybridAuth\Service\ConfigurationServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;

/**
 * Class ConfigurationService
 * @package Port1HybridAuth\Service
 */
class ConfigurationService implements ConfigurationServiceInterface
{
    
    const PROVIDER = 'Typo3';

    /**
     * @var ContextServiceInterface
     */
    protected $context;

    /**
     * @var \Shopware_Components_Config
     */
    protected $config;

    /**
     * @var \Shopware_Components_Snippet_Manager
     */
    protected $snippetManager;

    /**
     * @var \Port1HybridAuth\Service\ConfigurationService
     */
    protected $service;

    /**
     * ConfigurationService constructor.
     *
     * @param ContextServiceInterface $context
     * @param \Shopware_Components_Config $config
     * @param \Shopware_Components_Snippet_Manager $snippetManager
     * @param \Port1HybridAuth\Service\ConfigurationService $service
     */
    public function __construct(
        ContextServiceInterface $context,
        \Shopware_Components_Config $config,
        \Shopware_Components_Snippet_Manager $snippetManager,
        \Port1HybridAuth\Service\ConfigurationService $service
    ) {
        $this->context = $context;
        $this->config = $config;
        $this->snippetManager = $snippetManager;
        $this->service = $service;
    }

    public function getEnabledProviders()
    {
        $result = $this->service->getEnabledProviders();

        $provider = self::PROVIDER;
        if ((bool)$this->config->getByNamespace('Port1HybridAuthTypo3', strtolower($provider) . '_enabled')) {
            $label = $this->snippetManager->getNamespace('frontend/account/login')->get('SignInWith' . $provider);
            $result[$provider] = $label;
        }

        return $result;
    }

    public function isProviderEnabled($provider)
    {
        if ($provider === self::PROVIDER) {
            $result = (bool)$this->config->getByNamespace('Port1HybridAuthTypo3', strtolower($provider) . '_enabled');
        } else {
            $result = $this->service->isProviderEnabled($provider);
        }
        return $result;
    }

    public function getAllProviderConfigurations()
    {
        $result = $this->service->getAllProviderConfigurations();
        $config = $this->getProviderConfiguration(self::PROVIDER);
        if ($config !== false) {
            $result = array_replace_recursive($result, $this->getProviderConfiguration(self::PROVIDER));
        }
        return $result;
    }

    public function getProviderConfiguration($provider)
    {
        $result = false;

        if ($provider === self::PROVIDER) {
            if ($this->isProviderEnabled($provider)) {
                $configFile =  __DIR__ . '/../Configuration/config.php';
                $result = $this->service->getProviderConfigurationFromConfigFile($provider, $configFile, 'Port1HybridAuthTypo3');
            }
        } else {
            $result = $this->service->getProviderConfiguration($provider);
        }
        return $result;
    }

}
