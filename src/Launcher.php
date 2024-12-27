<?php

declare(strict_types=1);

namespace Drupal\Launcher;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;

final class Launcher
{
    public static function start(): int
    {
        $rootDir = getcwd();

        $dotenv = new Dotenv();
        $dotenv->load($rootDir . '/.env');

        $io = new SymfonyStyle(
            new StringInput(''),
            new ConsoleOutput(),
        );
        $php = new Php([$rootDir, 'bin', 'php']);
        $composer = new Composer([$rootDir, 'bin', 'composer'], $php);

        $package = getenv('LAUNCHER_TEMPLATE') ?: 'drupal/recommended-project';
        $projectDir = getenv('LAUNCHER_DIR') ?: 'drupal';
        $projectRoot = $rootDir . DIRECTORY_SEPARATOR . $projectDir;

        if (! is_dir($projectRoot)) {
            $command = [
                'create-project',
                $package,
                $projectDir,
            ];
            $flags = getenv('LAUNCHER_FLAGS');
            if ($flags) {
                $command = array_merge($command, ...explode(' ', $flags));
            }
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
            "--working-dir=$projectRoot",
        ];
        $webRoot = $composer->execute($command)->mustRun()->getOutput();
        $webRoot = trim($webRoot);

        $server = new Server($projectRoot . DIRECTORY_SEPARATOR . $webRoot, $php, '127.0.0.1', $io);
        return $server->start(new Browser($io));
    }
}
