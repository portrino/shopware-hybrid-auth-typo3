<?php
namespace Port1HybridAuthTypo3\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Components\Theme\LessDefinition;

/**
 * Class PluginAssetsCompilerSubscriber
 *
 * @package Port1HybridAuthTypo3\Subscriber
 */
class PluginAssetsCompilerSubscriber extends AbstractSubscriber
{

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Theme_Compiler_Collect_Plugin_Less' => 'onCollectLessFiles'
        ];
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
                sprintf(
                    '%1$s%2$sResources%2$sviews%2$sfrontend%2$s_public%2$ssrc%2$sless%2$shybrid_auth.less',
                    $this->getPath(),
                    \DIRECTORY_SEPARATOR
                )
            ],

            //import directory for less @import commands
            $lessDir
        );

        return new ArrayCollection([$lessDefinition]);
    }
}
