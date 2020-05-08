<?php

namespace SLLH\ComposerVersionsCheck\Tests;

use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\ArrayRepository;
use Composer\Repository\WritableArrayRepository;
use SLLH\ComposerVersionsCheck\VersionsCheck;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
class VersionsCheckTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ArrayRepository
     */
    private $distRepository;

    /**
     * @var WritableArrayRepository
     */
    private $localRepository;

    /**
     * @var RootPackage
     */
    private $rootPackage;

    /**
     * @var VersionsCheck
     */
    private $versionsCheck;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->distRepository = new ArrayRepository();
        $this->localRepository = new WritableArrayRepository();

        $this->rootPackage = new RootPackage('my/project', '1.0.0', '1.0.0');
        $this->versionsCheck = new VersionsCheck();
    }

    /**
     * @dataProvider getOutdatedDetectionTestData
     *
     * @param string $actualVersion
     * @param string $higherVersion
     * @param bool   $shouldBeUpdated
     * @param bool   $preferStable
     */
    public function testOutdatedDetection($actualVersion, $higherVersion, $shouldBeUpdated, $preferStable = false)
    {
        $this->distRepository->addPackage(new Package('foo/bar', $higherVersion, $higherVersion));
        $this->localRepository->addPackage(new Package('foo/bar', $actualVersion, $actualVersion));

        $this->rootPackage->setPreferStable($preferStable);
        $this->checkPackages();

        // Must have one outdatedPackage if this should be updated
        if (true === $shouldBeUpdated) {
            $this->assertAttributeCount(1, 'outdatedPackages', $this->versionsCheck);
            $this->assertSame(sprintf(<<<'EOF'
<warning>1 package is not up to date:</warning>

  - <info>foo/bar</info> (<comment>%s</comment>) latest is <comment>%s</comment>


EOF
                , $actualVersion, $higherVersion), $this->versionsCheck->getOutput());
        } else {
            $this->assertAttributeCount(0, 'outdatedPackages', $this->versionsCheck);
            $this->assertSame("<info>All packages are up to date.</info>\n", $this->versionsCheck->getOutput());
        }
    }

    /**
     * @return array
     */
    public function getOutdatedDetectionTestData()
    {
        return array(
            array('1.0.0', '1.0.0', false),
            array('1.0.0', '1.0.0', false),
            array('1.0.0', '2.0.0', true),
            array('2.0.0', '1.0.0', false),
            array('2.0.0', '2.0.0', false),
            array('2.0.0', '1.9.9', false),
            array('1.9.9', '2.0.0', true),
            array('2.0.0', '2.1.0-alpha1', true),        // Should be true because no stable package available
            array('2.0.0', '2.1.0-alpha1', false, true), // Should be false because of minimum stability
            array('2.0.0-alpha1', '2.1.0-alpha2', true),
        );
    }

    /**
     * @dataProvider getMultiplePackagesComparisonTestsData
     *
     * @param bool $preferStable
     * @param int  $outdatedPackagesCount
     */
    public function testMultiplePackagesComparison(array $packagesData, $preferStable, $outdatedPackagesCount)
    {
        $this->rootPackage->setMinimumStability('dev');
        $this->rootPackage->setPreferStable($preferStable);

        $shouldBeUpdatedOutput = $this->addPackagesAndGetShouldBeUpdatedOutput($packagesData);

        $this->checkPackages();

        $this->assertSame(sprintf(<<<'EOF'
<warning>%d packages are not up to date:</warning>

%s


EOF
        , $outdatedPackagesCount, implode("\n\n", $shouldBeUpdatedOutput)), $this->versionsCheck->getOutput());
    }

    /**
     * @return array
     */
    public function getMultiplePackagesComparisonTestsData()
    {
        return array(
            array(array(
                'foo/bar' => array('1.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'some/package' => array('1.0.4', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'vendor/package-1' => array('2.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), false),
                'vendor/up-to-date' => array('9.9.0', array('8.0.0', '9.9.0', '1.0-dev'), false),
                'vendor/dev-master' => array('9.9.0', array('8.0.0', '9.9.0', '10.0-dev'), '10.0-dev'),
                'vendor/package-2' => array('1.0.0', array('1.0.0', '1.0.1', '2.0.0', '2.0.0-alpha1'), '2.0.0'),
                'vendor/package-3' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), '2.0.0-alpha1'),
                'vendor/prefer-stable' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), '2.0.0-alpha1'),
            ), false, 6),
            array(array(
                'foo/bar' => array('1.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'some/package' => array('1.0.4', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'vendor/package-1' => array('2.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), false),
                'vendor/up-to-date' => array('9.9.0', array('8.0.0', '9.9.0', 'dev-master'), false),
                'vendor/package-2' => array('1.0.0', array('1.0.0', '1.0.1', '2.0.0', '2.0.0-alpha1'), '2.0.0'),
                'vendor/package-3' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), false),
                'vendor/prefer-stable' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), false),
            ), true, 3),
        );
    }

    public function testOutdatedWithLinks()
    {
        $this->distRepository->addPackage(new Package('foo/bar', '2.0', '2.0'));
        $this->localRepository->addPackage(new Package('foo/bar', '1.1', '1.1'));

        $linkedPackage = new Package('dummy/link', '1.0', '1.0');
        $linkedPackage->setRequires(array(new Link('dummy/link', 'foo/bar', null, '', '1.*')));
        $this->localRepository->addPackage($linkedPackage);

        // To test root package detection
        $this->localRepository->addPackage($this->rootPackage);

        $this->checkPackages();

        // Must have one outdatedPackage if this should be updated
        $this->assertAttributeCount(1, 'outdatedPackages', $this->versionsCheck);
        $this->assertSame(<<<'EOF'
<warning>1 package is not up to date:</warning>

  - <info>foo/bar</info> (<comment>1.1</comment>) latest is <comment>2.0</comment>
    Required by <info>dummy/link</info> (<comment>1.*</comment>)


EOF
            , $this->versionsCheck->getOutput());
        // Test with disabled show-links option
        $this->assertSame(<<<'EOF'
<warning>1 package is not up to date:</warning>

  - <info>foo/bar</info> (<comment>1.1</comment>) latest is <comment>2.0</comment>


EOF
            , $this->versionsCheck->getOutput(false));
    }

    /**
     * @dataProvider getRootOnlyPackageTestsData
     */
    public function testRootOnlyPackage(array $packagesData, array $packagesRequired, array $packagesDevRequired, $rootOnly, $outdatedPackagesCount)
    {
        $shouldBeUpdatedOutput = $this->addPackagesAndGetShouldBeUpdatedOutput($packagesData);
        $this->rootPackage->setRequires($packagesRequired);
        $this->rootPackage->setDevRequires($packagesDevRequired);

        $this->checkPackages($rootOnly);

        $this->assertSame(sprintf(<<<'EOF'
<warning>%d packages are not up to date:</warning>

%s


EOF
            , $outdatedPackagesCount, implode("\n\n", $shouldBeUpdatedOutput)), $this->versionsCheck->getOutput());
    }

    /**
     * @return array
     */
    public function getRootOnlyPackageTestsData()
    {
        return array(
            array(array(
                'foo/bar' => array('1.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'some/package' => array('1.0.4', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), false),
                'vendor/package-1' => array('2.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), false),
                'vendor/up-to-date' => array('9.9.0', array('8.0.0', '9.9.0', '1.0-dev'), false),
                'vendor/dev-master' => array('9.9.0', array('8.0.0', '9.9.0', '10.0-dev'), false),
                'vendor/package-2' => array('1.0.0', array('1.0.0', '1.0.1', '2.0.0', '2.0.0-alpha1'), '2.0.0'),
                'vendor/package-3' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), false),
                'vendor/prefer-stable' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), false),
            ), array(
                'foo/bar' => new Link('foo/bar', 'foo/bar'),
            ), array(
                'vendor/package-2' => new Link('vendor/package-2', 'vendor/package-2'),
            ), true, 2),
            array(array(
                'foo/bar' => array('1.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'some/package' => array('1.0.4', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), '2.0.0'),
                'vendor/package-1' => array('2.0.0', array('1.0.0', '1.0.1', '1.0.2', '1.0.4', '2.0.0'), false),
                'vendor/up-to-date' => array('9.9.0', array('8.0.0', '9.9.0', '1.0-dev'), false),
                'vendor/dev-master' => array('9.9.0', array('8.0.0', '9.9.0', '10.0-dev'), '10.0-dev'),
                'vendor/package-2' => array('1.0.0', array('1.0.0', '1.0.1', '2.0.0', '2.0.0-alpha1'), '2.0.0'),
                'vendor/package-3' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), '2.0.0-alpha1'),
                'vendor/prefer-stable' => array('1.0.1', array('1.0.0', '1.0.1', '2.0.0-alpha1'), '2.0.0-alpha1'),
            ), array(
                'foo/bar' => new Link('foo/bar', 'foo/bar'),
            ), array(
                'vendor/package-2' => new Link('vendor/package-2', 'vendor/package-2'),
            ), false, 6),
        );
    }

    private function addPackagesAndGetShouldBeUpdatedOutput(array $packagesData)
    {
        $shouldBeUpdatedOutput = array();

        foreach ($packagesData as $name => $packageData) {
            list($actualVersion, $availableVersions, $expectedVersion) = $packageData;
            $this->localRepository->addPackage(new Package($name, $actualVersion, $actualVersion));
            foreach ($availableVersions as $availableVersion) {
                $this->distRepository->addPackage(new Package($name, $availableVersion, $availableVersion));
            }

            if (false !== $expectedVersion) {
                $shouldBeUpdatedOutput[] = sprintf(
                    '  - <info>%s</info> (<comment>%s</comment>) latest is <comment>%s</comment>',
                    $name, $actualVersion, $expectedVersion
                );
            }
        }

        return $shouldBeUpdatedOutput;
    }

    /**
     * Calls VersionsCheck::checkPackages.
     */
    private function checkPackages($rootPackageOnly = false)
    {
        $this->versionsCheck->checkPackages($this->distRepository, $this->localRepository, $this->rootPackage, $rootPackageOnly);
    }
}
