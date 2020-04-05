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

use Composer\Composer;
use Composer\IO\IOInterface;
use function dirname;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function rmdir;
use RuntimeException;
use function sprintf;
use function sys_get_temp_dir;

/**
 * Phive executable downloader.
 *
 * @author MacFJA
 */
class PhiveDownloader
{
    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $inputOutput;

    public function __construct(Composer $composer, IOInterface $inputOutput)
    {
        $this->composer = $composer;
        $this->inputOutput = $inputOutput;
    }

    /**
     * Download and install the last Phive Phar if not already existing.
     */
    public function install(): void
    {
        $package = new PhivePackage();

        if (is_file($package->getBinaryPath())) {
            return;
        }

        $this->inputOutput->write('Downloading Phive...');
        $tmpPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'phive';
        $fileDownloader = $this->composer->getDownloadManager()->getDownloader('file');
        $fileDownloader->download(new PhivePackage(), $tmpPath);

        $phiveDestination = dirname($package->getBinaryPath());
        if (!is_dir($phiveDestination)) {
            if (!mkdir($phiveDestination, 0775, true) && !is_dir($phiveDestination)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $phiveDestination));
            }
        }

        rename($tmpPath.'/phive.phar', $package->getBinaryPath());
        rmdir($tmpPath);
        $this->inputOutput->write('Phive executable installed at '.$package->getBinaryPath());
    }
}
