<?php
namespace Port1HybridAuthTypo3\Subscriber;

use Port1HybridAuth\Service\AbstractAuthenticationService;
use Port1HybridAuth\Service\SingleSignOnService;
use Port1HybridAuthTypo3\Service\AuthenticationService\Typo3AuthenticationService;

/**
 * Class PluginFrontendSubscriber
 *
 * @package Port1HybridAuthTypo3\Subscriber
 */
class PluginFrontendSubscriber extends AbstractSubscriber
{

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend' => 'onFrontendPostDispatch'
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
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName(
            'Port1HybridAuthTypo3',
            $this->container->get('Shop')
        );
        $view->assign('Port1HybridAuthTypo3Config', $config);

        if ((bool)$config['general_force_sso'] === true) {
            /** @var \Enlight_Controller_Action $controller */
            $controller = $args->get('subject');

            $view = $controller->View();
            $view->addTemplateDir(
                sprintf(
                    '%1$s%2$sResources%2$sviews',
                    $this->getPath(),
                    \DIRECTORY_SEPARATOR
                )
            );

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

                if ($typo3AuthenticationService instanceof AbstractAuthenticationService) {
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
}
