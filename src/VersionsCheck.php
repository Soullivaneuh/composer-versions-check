<?php

namespace SLLH\ComposerVersionsCheck;

use Composer\Package\AliasPackage;
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\ArrayRepository;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Semver\Comparator;
use Composer\Semver\Constraint\Constraint;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class VersionsCheck
{
    /**
     * @var OutdatedPackage[]
     */
    private $outdatedPackages = array();

    public function checkPackages(ArrayRepository $distRepository, WritableRepositoryInterface $localRepository, RootPackageInterface $rootPackage, $rootPackagesOnly)
    {
        $packages = $localRepository->getPackages();
        $rootRequires = array_keys($rootPackage->getRequires() + $rootPackage->getDevRequires());

        foreach ($packages as $package) {
            // Do not compare aliases. Aliased packages are also provided.
            if ($package instanceof AliasPackage) {
                continue;
            }

            // No root package are ignored and it is one. Skip.
            if ($rootPackagesOnly && !\in_array($package->getName(), $rootRequires, true)) {
                continue;
            }

            // Old composer versions BC
            $versionConstraint = class_exists('Composer\Semver\Constraint\Constraint')
                ? new Constraint('>', $package->getVersion())
                : new VersionConstraint('>', $package->getVersion())
            ;

            $higherPackages = $distRepository->findPackages($package->getName(), $versionConstraint);

            // Remove not stable packages if unwanted
            if (true === $rootPackage->getPreferStable()) {
                $higherPackages = array_filter($higherPackages, function (PackageInterface $package) {
                    return 'stable' === $package->getStability();
                });
            }

            // We got higher packages! Let's push it.
            if (\count($higherPackages) > 0) {
                // Sort packages by highest version to lowest
                usort($higherPackages, function (PackageInterface $p1, PackageInterface $p2) {
                    return Comparator::compare($p1->getVersion(), '<', $p2->getVersion());
                });

                // Push actual and last package on outdated array
                array_push($this->outdatedPackages, new OutdatedPackage($package, $higherPackages[0], $this->getPackageDepends($localRepository, $package)));
            }
        }
    }

    /**
     * @param bool $showDepends
     *
     * @return string
     */
    public function getOutput($showDepends = true)
    {
        $output = array();

        if (0 === \count($this->outdatedPackages)) {
            $output[] = '<info>All packages are up to date.</info>';
        } else {
            $this->createNotUpToDateOutput($output, $showDepends);
        }

        return implode(PHP_EOL, $output).PHP_EOL;
    }

    /**
     * @return Link[]
     */
    private function getPackageDepends(WritableRepositoryInterface $localRepository, PackageInterface $needle)
    {
        $depends = array();

        foreach ($localRepository->getPackages() as $package) {
            // Skip root package
            if ($package instanceof RootPackageInterface) {
                continue;
            }

            foreach ($package->getRequires() as $link) {
                if ($link->getTarget() === $needle->getName() && !\in_array($link, $depends, true)) {
                    $depends[] = $link;
                }
            }
        }

        return $depends;
    }

    /**
     * @param bool $showLinks
     */
    private function createNotUpToDateOutput(array &$output, $showLinks = true)
    {
        $outdatedPackagesCount = \count($this->outdatedPackages);
        $output[] = sprintf(
            '<warning>%d %s not up to date:</warning>',
            $outdatedPackagesCount,
            1 !== $outdatedPackagesCount ? 'packages are' : 'package is'
        );
        $output[] = '';

        foreach ($this->outdatedPackages as $outdatedPackage) {
            $output[] = sprintf(
                '  - <info>%s</info> (<comment>%s</comment>) latest is <comment>%s</comment>',
                $outdatedPackage->getActual()->getPrettyName(),
                $outdatedPackage->getActual()->getPrettyVersion(),
                $outdatedPackage->getLast()->getPrettyVersion()
            );

            if (true === $showLinks) {
                foreach ($outdatedPackage->getLinks() as $depend) {
                    $output[] = sprintf(
                        '    Required by <info>%s</info> (<comment>%s</comment>)',
                        $depend->getSource(),
                        $depend->getPrettyConstraint()
                    );
                }
            }

            $output[] = '';
        }
    }
}
