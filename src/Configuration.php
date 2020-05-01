<?php

declare(strict_types=1);

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
use function in_array;

class Configuration
{
    /** @var null|Composer */
    private $composer;

    public function __construct(?Composer $composer = null)
    {
        $this->composer = $composer;
    }

    public function setComposer(Composer $composer): void
    {
        $this->composer = $composer;
    }

    /**
     * Get directory path of where Composer put binary.
     */
    public function getBinaryDirectory(): string
    {
        if (null === $this->composer) {
            return 'vendor/bin';
        }

        return (string) $this->composer->getConfig()->get('bin-dir');
    }

    /**
     * Indicate if phive.xml must be created/updated.
     */
    public function isTemporaryInstallation(): bool
    {
        return (bool) ($this->getExtraData('temporary') ?? false);
    }

    /**
     * Lookup at the composer package that must not be replaced.
     */
    public function isExclude(string $package): bool
    {
        $exclude = (array) ($this->getExtraData('exclude') ?? []);

        return in_array($package, $exclude, true);
    }

    /**
     * @return null|mixed
     */
    private function getExtraData(string $key)
    {
        if (null === $this->composer) {
            return null;
        }

        return $this->composer->getPackage()->getExtra()['composer-phar-bin'][$key] ?? null;
    }
}
