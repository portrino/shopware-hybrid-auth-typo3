<?php
namespace Port1HybridAuthTypo3;

/**
 * Copyright (C) portrino GmbH - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by (c) Axel Boeswetter <boeswetter@portrino.de>, portrino GmbH
 */

use ComposerLocator;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Customer\Customer;

/**
 * Class Port1HybridAuthTypo3
 *
 * @package Port1HybridAuthTypo3
 */
class Port1HybridAuthTypo3 extends Plugin
{

    /**
     * @param InstallContext $context
     * @throws \Exception
     */
    public function install(InstallContext $context)
    {
        $this->addIdentityFieldsToUser();
        $this->activateHybridAuthTypo3Provider();

        parent::install($context);
    }

    /**
     * @param ActivateContext $context
     * @throws \Exception
     */
    public function activate(ActivateContext $context)
    {
        $this->addIdentityFieldsToUser();
        $this->activateHybridAuthTypo3Provider();

        parent::activate($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        parent::uninstall($context);
    }

    /**
     * @throws \Exception
     */
    private function addIdentityFieldsToUser()
    {
        /** @var CrudService $service */
        $service = $this->container->get('shopware_attribute.crud_service');

        $service->update('s_user_attributes', 'typo3_identity', 'string', [
            'label' => 'Identity TYPO3',

            //user has the opportunity to translate the attribute field for each shop
            'translatable' => false,

            //attribute will be displayed in the backend module
            'displayInBackend' => true,

            //in case of multi_selection or single_selection type, article entities can be selected,
            'entity' => Customer::class,

            //numeric position for the backend view, sorted ascending
            'position' => 100,

            //user can modify the attribute in the free text field module
            'custom' => false,
        ]);
    }

    /**
     * @throws \Exception
     */
    private function activateHybridAuthTypo3Provider()
    {
        /** @var Port1HybridAuthTypo3 $port1HybridAuth */
        $port1HybridAuth = $this->container->get('kernel')->getPlugins()['Port1HybridAuth'];
        if ($port1HybridAuth !== null) {
            if (ComposerLocator::isInstalled('hybridauth/hybridauth')) {
                $hybridauthRootPath = ComposerLocator::getPath('hybridauth/hybridauth');
                $hybridauthHybridPath = sprintf(
                    '%1$s%2$shybridauth%2$sHybrid',
                    rtrim($hybridauthRootPath, \DIRECTORY_SEPARATOR),
                    \DIRECTORY_SEPARATOR
                );
                $hybridauthTypo3Path = sprintf(
                    '%1$s%2$svendor%2$shybridauth-additional-providers%2$shybridauth-typo3',
                    rtrim(__DIR__, \DIRECTORY_SEPARATOR),
                    \DIRECTORY_SEPARATOR
                );
            }

            if (
                file_exists($hybridauthHybridPath) && is_dir($hybridauthHybridPath)
                    && file_exists($hybridauthTypo3Path) && is_dir($hybridauthTypo3Path)
            ) {
                $source = $hybridauthTypo3Path;
                $dest = $hybridauthHybridPath;

                /** @var \RecursiveDirectoryIterator $dirIterator */
                $dirIterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($dirIterator as $item) {
                    $subItem = $dest . DIRECTORY_SEPARATOR . $dirIterator->getSubPathName();
                    if ($item->isDir()) {
                        if (!file_exists($subItem)) {
                            if (!mkdir($subItem) && !is_dir($subItem)) {
                                throw new \RuntimeException(sprintf('Directory "%s" was not created', $subItem));
                            }
                        }
                    } else {
                        copy($item, $subItem);
                    }
                }
            }
        } else {
            throw new \Exception('Please install plugin Port1HybridAuth first!', 1497531163);
        }
    }
}
