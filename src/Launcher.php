<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Launcher
{
    public static function start(): int
    {
        $rootDir = getcwd();

        $io = new SymfonyStyle(
            new StringInput(''),
            new ConsoleOutput(),
        );
        $php = new Php([$rootDir, 'bin', 'php'], '8.3.14', $io);
        $composer = new Composer([$rootDir, 'bin', 'composer'], '2.8.4', $php, $io);

        $projectRoot = $rootDir . DIRECTORY_SEPARATOR . 'cms';
        if (! is_dir($projectRoot)) {
            $command = [
                'create-project',
                'drupal/cms',
                '--stability=rc',
            ];
            $composer->execute($command)
                ->setTimeout(300)
                ->setWorkingDirectory($rootDir)
                ->mustRun(function (string $type, string $buffer) use ($io): void {
                    $io->write($buffer);
                });
        }

        $command = [
            'config',
            'extra.drupal-scaffold.locations.web-root',
            "--working-dir={$projectRoot}",
        ];
        $webRoot = $composer->execute($command)->mustRun()->getOutput();
        $webRoot = trim($webRoot);

        $server = new Server($projectRoot . DIRECTORY_SEPARATOR . $webRoot, $php, '127.0.0.1', $io);
        return $server->start(new Browser($io));
    }
}
