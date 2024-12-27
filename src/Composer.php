<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Process\Process;

final class Composer
{
    private readonly string $path;

    public function __construct(
        array $path,
        private readonly Php $php,
    ) {
        $this->path = implode(DIRECTORY_SEPARATOR, $path);
    }

    public function execute(array $command): Process
    {
        return $this->php->execute([$this->path, ...$command]);
    }

}
