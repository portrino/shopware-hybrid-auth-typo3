<?php
namespace Port1HybridAuthTypo3\Composer;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;

/**
 * Class AdditionalHybridAuthProvider
 *
 * @package Port1HybridAuthTypo3\Composer
 */
class AdditionalHybridAuthProvider
{

    /**
     * @param Event $event
     */
    public static function installTypo3(Event $event)
    {
        /** @var InstallationManager $installationManager */
        $installationManager = $event->getComposer()->getInstallationManager();
        /** @var RepositoryManager $repositoryManager */
        $repositoryManager = $event->getComposer()->getRepositoryManager();
        /** @var InstalledFilesystemRepository $localRepository */
        $localRepository = $repositoryManager->getLocalRepository();

        $hybridAuthInstallPath = null;
        $packages = $localRepository->getPackages();
        /** @var Package $package */
        foreach ($packages as $package) {
            if ($package->getName() === 'hybridauth/hybridauth') {
                $hybridAuthInstallPath = $installationManager->getInstallPath($package);
                break;
            }
        }
        $pluginInstallPath = $installationManager->getInstallPath($event->getComposer()->getPackage());

        if ($hybridAuthInstallPath !== null && is_dir($hybridAuthInstallPath)) {
            $sourcePaths = [
                'TYPO3 Provider' => $pluginInstallPath . 'vendor/hybridauth-additional-providers/hybridauth-typo3/Providers',
                'TYPO3 OAuth2 Client' => $pluginInstallPath . 'vendor/hybridauth-additional-providers/hybridauth-typo3/thirdparty'
            ];
            $destinationPath = $hybridAuthInstallPath . DIRECTORY_SEPARATOR . 'hybridauth' . DIRECTORY_SEPARATOR . 'Hybrid';
            if (is_dir($destinationPath)) {
                foreach ($sourcePaths as $key => $sourcePath) {
                    $result = exec('cp -R ' . $sourcePath . ' ' . $destinationPath . ' && echo "true" || echo "false"');
                    if ((bool)$result === true) {
                        $event->getIO()->write('Copied HybridAuth "' . $key . '"" successfully.');
                    } else {
                        $event->getIO()->writeError(
                            'Error: Copying HybridAuth "' . $key . '" failed!'
                            . chr(10)
                            . var_export([
                                'sourcePath' => $sourcePath,
                                'destinationPath' => $destinationPath
                            ])
                        );
                    }
                }
            }
        }
    }
}