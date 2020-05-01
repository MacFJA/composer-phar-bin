<?php

/*
 * Copyright MacFJA
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace MacFJA\ComposerPharBin;

use function array_search;
use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use function file_exists;
use function is_file;
use function is_string;
use function unlink;
use Webmozart\PathUtil\Path;

/**
 * Entry point of the Composer plugin.
 *
 * @suppress PhanUnreferencedClass
 * @psalm-suppress UnusedClass
 *
 * @author MacFJA
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var PhiveRunner */
    private $runner;

    /** @var Configuration */
    private $configuration;

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
        $this->runner = new PhiveRunner();
        $this->configuration = new Configuration();
    }

    public function activate(Composer $composer, IOInterface $inputOutput): void
    {
        (new PhiveDownloader($composer, $inputOutput))->install();
        $this->configuration->setComposer($composer);
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'onPreInstall',
            ScriptEvents::PRE_UPDATE_CMD => 'onPreInstall',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'onPackageRemoval',
        ];
    }

    /**
     * Replace dev dependencies with Phar binary when possible and allowed.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function onPreInstall(Event $event): void
    {
        $packages = $event->getComposer()->getPackage()->getDevRequires();
        $replaces = $event->getComposer()->getPackage()->getReplaces();

        $toDownload = [];

        foreach ($packages as $package) {
            if ($this->configuration->isExclude($package->getTarget())) {
                continue;
            }

            $alias = $this->getPackageAlias($package->getTarget());

            if (is_string($alias)) {
                $alias .= ':'.$package->getPrettyConstraint();
                $toDownload[] = $alias;
                $replaces[] = $package;
            }
        }
        $event->getComposer()->getPackage()->setReplaces($replaces);

        $event->getIO()->write('<info>Installing PHARs</info>');
        $this->runner->install($this->configuration, $event->getIO(), $toDownload);
    }

    /**
     * Remove binary on package removal.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function onPackageRemoval(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if (!($operation instanceof UninstallOperation)) {
            return;
        }
        $package = $operation->getPackage();

        if ($this->configuration->isExclude($package->getName())) {
            return;
        }
        $alias = $this->getPackageAlias($package->getName());

        if (is_string($alias)) {
            if ($this->configuration->isTemporaryInstallation()) {
                $this->removeAliasFile($event->getIO(), $alias);

                return;
            }
            $this->runner->remove($event->getIO(), $alias);
        }
    }

    /**
     * Get the Phive alias of a composer package.
     */
    protected function getPackageAlias(string $package): ?string
    {
        $phiveRunner = new PhiveRunner();

        $list = $phiveRunner->list();

        $key = array_search($package, $list, true);

        return is_string($key) ? $key : null;
    }

    private function removeAliasFile(IOInterface $inputOutput, string $alias): void
    {
        $path = Path::canonicalize($this->configuration->getBinaryDirectory().'/'.$alias);

        if (!file_exists($path) || !is_file($path)) {
            $inputOutput->writeError('<warning>No file to remove</warning>');

            return;
        }

        unlink($path);
    }
}
