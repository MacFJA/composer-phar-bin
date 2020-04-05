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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
use function array_merge;
use Composer\Composer;
use Composer\IO\IOInterface;
use function count;
use function file_get_contents;
use function simplexml_load_string;
use function str_replace;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;
use function trim;
use Webmozart\PathUtil\Path;

/**
 * Phive commands runner.
 *
 * @author MacFJA
 */
class PhiveRunner
{
    /**
     * Run the install command of the Phive binary.
     *
     * @param array<string> $packages
     */
    public function install(Composer $composer, IOInterface $inputOutput, array $packages): bool
    {
        if (0 === count($packages)) {
            return true;
        }

        $binDir = (string) $composer->getConfig()->get('bin-dir');

        $parameters = [
            'install',
            '--target',$binDir,
            '--temporary',
            '--force-accept-unsigned'
        ];

        foreach ($packages as $package) {
            $parameters[] = $package;
        }

        $process = $this->getBaseProcess($parameters);
        $process->setTty(true);
        $exitCode = $process->run();

        if (!$process->isSuccessful()) {
            $inputOutput->writeError('<error>'.trim($process->getErrorOutput()).'</error>');

            throw new \RuntimeException(sprintf('Phar installation exit with the code %d', $exitCode));
        }

        return true;
    }

    /**
     * Get the version of the Phive binary.
     */
    public function version(): string
    {
        $process = $this->getBaseProcess(['version']);
        $process->run();

        return trim($process->getOutput());
    }

    /**
     * Parse repositories (Phar-io and local) to extract alias and composer name.
     */
    public function list(): array
    {
        $aliases = [];
        foreach ([Path::canonicalize('~/.phive/repositories.xml'), Path::canonicalize('~/.phive/local.xml')] as $path) {
            $aliases = array_merge($aliases, $this->extractAlias($path));
        }

        return $aliases;
    }

    private function getBaseProcess(array $arguments= []): Process
    {
        $composerPackage = new PhivePackage();

        $commandParts = array_merge(
            [(new PhpExecutableFinder())->find(false) ?: 'php', $composerPackage->getBinaryPath()],
            $arguments
        );

        if (class_exists(ProcessBuilder::class)) {
            return (new ProcessBuilder($commandParts))->getProcess();
        }

        return new Process($commandParts);
    }

    private function extractAlias(string $fromPath): array
    {
        $xml = simplexml_load_string(file_get_contents($fromPath));

        $nodes = $xml->xpath('//*[local-name()="phar"]');
        $result = [];
        foreach ($nodes as $node) {
            $result[(string) $node['alias']] = (string) $node['composer'];
        }

        return $result;
    }
}
