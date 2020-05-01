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

use function array_merge;
use function class_exists;
use Composer\IO\IOInterface;
use function count;
use function file_exists;
use function file_get_contents;
use function is_file;
use RuntimeException;
use function simplexml_load_string;
use function sprintf;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
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
    public function install(Configuration $configuration, IOInterface $inputOutput, array $packages): bool
    {
        if (0 === count($packages)) {
            return true;
        }

        $parameters = [
            'install',
            '--target', $configuration->getBinaryDirectory(),
            '--force-accept-unsigned',
        ];

        if ($configuration->isTemporaryInstallation()) {
            $parameters[] = '--temporary';
        }

        foreach ($packages as $package) {
            $parameters[] = $package;
        }

        return $this->runCommand($inputOutput, 'Phar installation exit with the code %d', $parameters);
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

    /**
     * Run the remove command of the Phive binary.
     */
    public function remove(IOInterface $inputOutput, string $alias): bool
    {
        $parameters = [
            'remove',
            $alias,
        ];

        return $this->runCommand($inputOutput, 'Phar removal exit with the code %d', $parameters);
    }

    /**
     * @param array<string> $parameters
     */
    private function runCommand(IOInterface $inputOutput, string $exceptionMessage, array $parameters = []): bool
    {
        $process = $this->getBaseProcess($parameters);
        $process->setTty(true);
        $exitCode = $process->run();

        if (!$process->isSuccessful()) {
            $inputOutput->writeError('<error>'.trim($process->getErrorOutput()).'</error>');

            throw new RuntimeException(sprintf($exceptionMessage, $exitCode));
        }

        return true;
    }

    /**
     * @param array<string> $arguments
     */
    private function getBaseProcess(array $arguments = []): Process
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

    /**
     * @return array<string,string>
     */
    private function extractAlias(string $fromPath): array
    {
        if (!file_exists($fromPath) || !is_file($fromPath)) {
            return [];
        }
        $rawContent = file_get_contents($fromPath);

        if (false === $rawContent) {
            return [];
        }

        $xml = simplexml_load_string($rawContent);

        if (false === $xml) {
            return [];
        }

        $nodes = $xml->xpath('//*[local-name()="phar"]');

        if (false === $nodes) {
            return [];
        }

        $result = [];
        foreach ($nodes as $node) {
            $result[(string) $node['alias']] = (string) $node['composer'];
        }

        return $result;
    }
}
