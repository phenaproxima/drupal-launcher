<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;

require_once 'vendor/autoload.php';

function _open_browser(string $url, StyleInterface $io): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Handle escaping ourselves.
        $command = 'start "web" "' . $url . '""';
    } else {
        $url = escapeshellarg($url);

        try {
            $command = match (PHP_OS_FAMILY) {
                'Linux' => "xdg-open $url",
                'Darwin' => "open $url",
            };
        } catch (\UnhandledMatchError) {
            $io->error("Could not figure out how to open a browser. Visit $url to get started.");
            return;
        }
    }

    if ($io->isVerbose()) {
        $io->writeln("<info>Browser command:</info> $command");
    }

    // Need to escape double quotes in the command so the PHP will work.
    $command = str_replace('"', '\"', $command);
    // Sleep for 2 seconds before opening the browser. This allows the command
    // to start up the PHP built-in web server in the meantime. We use a
    // PhpProcess so that Windows powershell users also get a browser opened
    // for them.
    $process = new PhpProcess("<?php sleep(2); passthru(\"$command\"); ?>");
    $process->start();
}

function _find_port(string $host): int|false
{
    $port = 8888;
    while ($port >= 8888 && $port <= 9999) {
        $connection = @fsockopen($host, $port);
        if (is_resource($connection)) {
            // Port is being used.
            fclose($connection);
        }
        else {
            // Port is available.
            return $port;
        }
        $port++;
    }
    return false;
}

$rootDir = getcwd();

$settings = $rootDir . DIRECTORY_SEPARATOR . 'launcher.ini';
if (file_exists($settings)) {
    $settings = parse_ini_file($settings, true);
}

$io = new SymfonyStyle(
    new StringInput(''),
    new ConsoleOutput(),
);

$php = implode(DIRECTORY_SEPARATOR, [$rootDir, 'bin', 'php']);
if (PHP_OS_FAMILY === 'Windows') {
    $php .= '.exe';
}

$composer = implode(DIRECTORY_SEPARATOR, [$rootDir, 'bin', 'composer']);

$package = $settings['project']['template'] ?? 'drupal/recommended-project';
$projectDir = $settings['project']['dir'] ?? 'drupal';
$projectRoot = $rootDir . DIRECTORY_SEPARATOR . $projectDir;

if (! is_dir($projectRoot)) {
    $command = [
        $php,
        $composer,
        'create-project',
        $package,
        $projectDir,
    ];
    $flags = $settings['project']['flags'] ?? null;
    if ($flags) {
        array_push($command, ...explode(' ', $flags));
    }
    (new Process($command))
        ->setTimeout(300)
        ->setWorkingDirectory($rootDir)
        ->mustRun(function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        });
}

$command = [
    $php,
    $composer,
    'config',
    'extra.drupal-scaffold.locations.web-root',
    "--working-dir=$projectRoot",
];
$webRoot = (new Process($command))->mustRun()->getOutput();
$webRoot = trim($webRoot);

$host = '127.0.0.1';
$port = _find_port($host);

if ($port === false) {
    $io->error('Could not start the web server because there were no open ports.');
    return 1;
}

$host .= ":$port";
_open_browser('http://' . $host, $io);

return (new Process([$php, '-S', $host, '.ht.router.php']))
    ->setWorkingDirectory($projectRoot . DIRECTORY_SEPARATOR . $webRoot)
    ->setTimeout(null)
    ->run();
