<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Process\Process;

final class Php
{
    public function __construct(
        private readonly array $path,
    ) {}

    private static function background(string $command): bool
    {
        return PHP_OS_FAMILY === 'Windows'
            ? str_starts_with($command, 'start /b ')
            : str_ends_with($command, ' &');
    }

    public function execute(string|array $command): Process
    {
        $path = $this->getPath();

        if (is_string($command) && self::background($command)) {
            return Process::fromShellCommandline("$path $command", timeout: null);
        } else {
            return new Process([$path, ...(array) $command]);
        }
    }

    private function getPath(): string
    {
        $path = $this->path;
        if (PHP_OS_FAMILY === 'Windows') {
            $path[] = 'php.exe';
        }
        return implode(DIRECTORY_SEPARATOR, $path);
    }
}
