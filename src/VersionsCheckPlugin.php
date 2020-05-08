<?php

namespace SLLH\ComposerVersionsCheck;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class VersionsCheckPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var VersionsCheck
     */
    private $versionsCheck;

    /**
     * @var bool
     */
    private $preferLowest;

    /**
     * @var array
     */
    private $options = array();

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->versionsCheck = new VersionsCheck();
        $this->options = $this->resolveOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::COMMAND => array(
                array('command'),
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('postUpdate', -100),
            ),
        );
    }

    public function command(CommandEvent $event)
    {
        $input = $event->getInput();
        $this->preferLowest = $input->hasOption('prefer-lowest') && true === $input->getOption('prefer-lowest');
    }

    public function postUpdate(Event $event)
    {
        if (true === $this->preferLowest) {
            return;
        }

        $this->checkVersions($this->composer->getRepositoryManager(), $this->composer->getPackage());
    }

    /**
     * Tries to get plugin options and resolves them.
     *
     * @return array
     */
    private function resolveOptions()
    {
        $pluginConfig = $this->composer->getConfig()
            ? $this->composer->getConfig()->get('sllh-composer-versions-check')
            : null
        ;

        $options = array(
            'root-packages-only' => false,
            'show-links' => false,
        );

        if (null === $pluginConfig) {
            return $options;
        }

        $options['root-packages-only'] = isset($pluginConfig['root-packages-only']) ? (bool) $pluginConfig['root-packages-only'] : $options['root-packages-only'];
        $options['show-links'] = isset($pluginConfig['show-links']) ? (bool) $pluginConfig['show-links'] : $options['show-links'];

        return $options;
    }

    private function checkVersions(RepositoryManager $repositoryManager, RootPackageInterface $rootPackage)
    {
        foreach ($repositoryManager->getRepositories() as $repository) {
            $this->versionsCheck->checkPackages(
                $repository,
                $repositoryManager->getLocalRepository(),
                $rootPackage,
                $this->options['root-packages-only']
            );
        }

        $this->io->write($this->versionsCheck->getOutput($this->options['show-links']), false);
    }
}
