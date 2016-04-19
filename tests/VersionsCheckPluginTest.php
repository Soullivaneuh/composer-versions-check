<?php

namespace SLLH\ComposerVersionsCheck\Tests;

use Composer\Command\UpdateCommand;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginManager;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableArrayRepository;
use Composer\Script\ScriptEvents;
use SLLH\ComposerVersionsCheck\VersionsCheckPlugin;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
class VersionsCheckPluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BufferIO
     */
    private $io;

    /**
     * @var Composer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $composer;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->io = new BufferIO();
        $this->composer = $this->getMock('Composer\Composer');

        $this->composer->expects($this->any())->method('getConfig')
            ->willReturn(new Config());
        $this->composer->expects($this->any())->method('getPackage')
            ->willReturn(new RootPackage('my/project', '1.0.0', '1.0.0'));
        $this->composer->expects($this->any())->method('getPluginManager')
            ->willReturn(new PluginManager($this->io, $this->composer));
        $this->composer->expects($this->any())->method('getEventDispatcher')
            ->willReturn(new EventDispatcher($this->composer, $this->io));
        $this->composer->expects($this->any())->method('getRepositoryManager')
            ->willReturn(new RepositoryManager($this->io, new Config()));
    }

    /**
     * @dataProvider getTestOptionsData
     *
     * @param array|null $configData
     * @param array      $expectedOptions
     */
    public function testOptions($configData, array $expectedOptions)
    {
        if (null === $configData) {
            $this->composer->expects($this->any())->method('getConfig')
                ->willReturn(null);
        } else {
            $config = new Config(false);
            $config->merge($configData);
            $this->composer->expects($this->any())->method('getConfig')
                ->willReturn($config);
        }

        $plugin = new VersionsCheckPlugin();
        $plugin->activate($this->composer, $this->io);

        $this->assertAttributeSame($expectedOptions, 'options', $plugin);
    }

    public function getTestOptionsData()
    {
        return array(
            array(
                null,
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(),
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(
                    'config' => array(
                        'sllh-composer-versions-check' => array(),
                    ),
                ),
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(
                    'config' => array(
                        'sllh-composer-versions-check' => null,
                    ),
                ),
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(
                    'config' => array(
                        'sllh-composer-versions-check' => false,
                    ),
                ),
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(
                    'config' => array(
                        'sllh-composer-versions-check' => array(
                            'show-links' => true,
                        ),
                    ),
                ),
                array(
                    'show-links' => true,
                ),
            ),
            array(
                array(
                    'config' => array(
                        'sllh-composer-versions-check' => array(
                            'show-links' => false,
                        ),
                    ),
                ),
                array(
                    'show-links' => false,
                ),
            ),
        );
    }

    public function testPluginRegister()
    {
        $plugin = new VersionsCheckPlugin();
        $this->addComposerPlugin($plugin);

        $this->assertSame(array($plugin), $this->composer->getPluginManager()->getPlugins());
        $this->assertAttributeInstanceOf('Composer\Composer', 'composer', $plugin);
        $this->assertAttributeInstanceOf('Composer\IO\IOInterface', 'io', $plugin);
        $this->assertAttributeInstanceOf('SLLH\ComposerVersionsCheck\VersionsCheck', 'versionsCheck', $plugin);
    }

    public function testUpdateCommand()
    {
        $this->addComposerPlugin(new VersionsCheckPlugin());

        $localRepository = new WritableArrayRepository();
        $localRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $this->composer->getRepositoryManager()->setLocalRepository($localRepository);

        $distRepository = new ArrayRepository();
        $distRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $distRepository->addPackage(new Package('foo/bar', '1.0.1', '1.0.1'));
        $distRepository->addPackage(new Package('foo/bar', '2.0.0', '2.0.0'));
        $this->composer->getRepositoryManager()->addRepository($distRepository);

        $this->composer->getEventDispatcher()->dispatchScript(ScriptEvents::POST_UPDATE_CMD);

        $this->assertSame(<<<'EOF'
<warning>1 package is not up to date:</warning>

  - foo/bar (1.0.0) latest is 2.0.0


EOF
            , $this->io->getOutput());
    }

    public function testPreferLowest()
    {
        $this->addComposerPlugin(new VersionsCheckPlugin());

        $localRepository = new WritableArrayRepository();
        $localRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $this->composer->getRepositoryManager()->setLocalRepository($localRepository);

        $distRepository = new ArrayRepository();
        $distRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $distRepository->addPackage(new Package('foo/bar', '2.0.0', '2.0.0'));
        $this->composer->getRepositoryManager()->addRepository($distRepository);

        $updateCommand = new UpdateCommand();
        $input = new ArrayInput(array('update'), $updateCommand->getDefinition());
        $input->setOption('prefer-lowest', true);
        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'update', $input, new NullOutput());
        $this->composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);
        $this->composer->getEventDispatcher()->dispatchScript(ScriptEvents::POST_UPDATE_CMD);

        $this->assertSame('', $this->io->getOutput(), 'Plugin should not be runned.');
    }

    public function testPreferLowestNotExists()
    {
        $this->addComposerPlugin(new VersionsCheckPlugin());

        $localRepository = new WritableArrayRepository();
        $localRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $this->composer->getRepositoryManager()->setLocalRepository($localRepository);

        $distRepository = new ArrayRepository();
        $distRepository->addPackage(new Package('foo/bar', '1.0.0', '1.0.0'));
        $distRepository->addPackage(new Package('foo/bar', '2.0.0', '2.0.0'));
        $this->composer->getRepositoryManager()->addRepository($distRepository);

        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'update', new ArrayInput(array()), new NullOutput());
        $this->composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);
        $this->composer->getEventDispatcher()->dispatchScript(ScriptEvents::POST_UPDATE_CMD);

        $this->assertSame(<<<'EOF'
<warning>1 package is not up to date:</warning>

  - foo/bar (1.0.0) latest is 2.0.0


EOF
            , $this->io->getOutput());
    }

    private function addComposerPlugin(PluginInterface $plugin)
    {
        $pluginManagerReflection = new \ReflectionClass($this->composer->getPluginManager());
        $addPluginReflection = $pluginManagerReflection->getMethod('addPlugin');
        $addPluginReflection->setAccessible(true);
        $addPluginReflection->invoke($this->composer->getPluginManager(), $plugin);
    }
}
