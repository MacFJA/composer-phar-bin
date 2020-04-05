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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use function in_array;
use function is_string;

/**
 * Entry point of the Composer plugin.
 *
 * @suppress PhanUnreferencedClass
 *
 * @author MacFJA
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $inputOutput): void
    {
        (new PhiveDownloader($composer, $inputOutput))->install();
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'onPreInstall',
            ScriptEvents::PRE_UPDATE_CMD => 'onPreInstall',
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

        $phiveRunner = new PhiveRunner();

        $toDownload = [];

        foreach ($packages as $package) {
            if ($this->isExclude($package->getTarget(), $event->getComposer())) {
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
        $phiveRunner->install($event->getComposer(), $event->getIO(), $toDownload);
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

    /**
     * Lookup at the composer package that must not be replaced.
     */
    protected function isExclude(string $package, Composer $composer): bool
    {
        $exclude = (array) ($composer->getPackage()->getExtra()['composer-phar-bin']['exclude'] ?? []);

        return in_array($package, $exclude, true);
    }
}
