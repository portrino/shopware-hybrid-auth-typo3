<?php
namespace Port1HybridAuthTypo3;

/**
 * Copyright (C) portrino GmbH - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by (c) Axel Boeswetter <boeswetter@portrino.de>, portrino GmbH
 */

use Doctrine\Common\Collections\ArrayCollection;
use Port1HybridAuth\Service\AbstractAuthenticationService;
use Port1HybridAuth\Service\SingleSignOnService;
use Port1HybridAuthTypo3\Service\AuthenticationService\Typo3AuthenticationService;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Theme\LessDefinition;
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
        $this->copyHybridAuthTypo3Provider();

        parent::install($context);
    }

    /**
     * @param ActivateContext $context
     * @throws \Exception
     */
    public function activate(ActivateContext $context)
    {
        $this->addIdentityFieldsToUser();
        $this->copyHybridAuthTypo3Provider();

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
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onFrontendPostDispatch',
            'Theme_Compiler_Collect_Plugin_Less' => 'onCollectLessFiles'
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @throws \Exception
     */
    public function onFrontendPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->get('subject');
        /** @var \Enlight_View_Default $view */
        $view = $controller->View();

        /** @var array $config Plugin configuration for current active shop in frontend */
        $config = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName(
            'Port1HybridAuthTypo3',
            $this->container->get('Shop')
        );
        $view->assign('Port1HybridAuthTypo3Config', $config);

        if ((bool)$config['general_force_sso'] === true) {
            /** @var \Enlight_Controller_Action $controller */
            $controller = $args->get('subject');

            $view = $controller->View();
            $view->addTemplateDir($this->getPath() . '/Resources/views');

            $controllerName = $controller->Front()->Request()->getControllerName();
            $actionName = $controller->Front()->Request()->getActionName();

            /**
             * do not authenticate when user presses logout or an forbidden redirect form auth before takes place!
             */
            if (!($controllerName === 'account' && $actionName === 'logout') &&
                !($controllerName === 'typo3login' && $actionName === 'forbidden') &&
                !($controllerName === 'typo3login' && $actionName === 'logout')
            ) {

                /** @var SingleSignOnService $singleSignOnService */
                $singleSignOnService = $this->container->get('port1_hybrid_auth.single_sign_on_service');
                $userIsLoggedIn = $singleSignOnService->loginAndRegisterVia('typo3');

                if ((bool)$userIsLoggedIn === false) {
                    $controller->forward('forbidden', 'typo3login');
                }
            }

            /**
             * do trigger sso logout
             */
            if ($controllerName === 'account' && $actionName === 'logout') {
                /** @var Typo3AuthenticationService $typo3AuthenticationService */
                $typo3AuthenticationService = $this->container->get('port1_hybrid_auth_typo3.typo3_authentication_service');

                if ($typo3AuthenticationService != null && $typo3AuthenticationService instanceof AbstractAuthenticationService) {
                    $typo3AuthenticationService->logout();
                }

                $controller->redirect(
                    [
                        'controller' => 'typo3login',
                        'action' => 'logout'
                    ]
                );
            }
        }
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return ArrayCollection
     */
    public function onCollectLessFiles(\Enlight_Event_EventArgs $args)
    {
        $lessDir = $this->getPath() . '/Resources/views/frontend/_public/src/less/';
        $lessDefinition = new LessDefinition(
        // less configuration variables
            [],

            // less files which should be compiled
            [
                $lessDir . 'hybrid_auth.less'
            ],

            //import directory for less @import commands
            $lessDir
        );

        return new ArrayCollection([$lessDefinition]);
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
    private function copyHybridAuthTypo3Provider()
    {
        /** @var Port1HybridAuthTypo3 $port1HybridAuth */
        $port1HybridAuth = Shopware()->Container()->get('kernel')->getPlugins()['Port1HybridAuth'];
        if ($port1HybridAuth !== null) {
            $this->copy_recursive(
                $this->getPath() . '/vendor/hybridauth-additional-providers/hybridauth-typo3/',
                Shopware()->DocPath() . '/vendor/hybridauth/hybridauth/hybridauth/Hybrid/'
            );
        } else {
            throw new \Exception('Please install plugin Port1HybridAuth first!', 1497531163);
        }
    }

    /**
     * @param string $src
     * @param string $dst
     */
    private function copy_recursive($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    $this->copy_recursive($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);
    }

}
