<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once 'vendor/autoload.php';

// The phar is built with an autoloader that has no dev dependencies,
// so we need to explicitly tell Composer where Finder is.
$autoloader->addPsr4('Symfony\\Component\\Finder\\', 'vendor/symfony/finder');

$fileSystem = new Filesystem();

$pharFile = 'launcher.phar';
if (file_exists($pharFile)) {
  $fileSystem->remove($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

['name' => $my_name] = InstalledVersions::getRootPackage();

// List all our production dependencies (any installed package that is
// not a dev dependency).
$dependencies = InstalledVersions::getAllRawData();
$dependencies = array_filter(
    $dependencies[0]['versions'],
    fn ($info) => $info['dev_requirement'] === false,
);
// The package we're building is not a dependency. :)
unset($dependencies[$my_name]);

$finder = Finder::create()
  ->files()
  ->in([
      'src',
      'vendor/composer',
      ...array_map(
        fn (string $path): string => Path::makeRelative($path, __DIR__),
        array_column($dependencies, 'install_path'),
      ),
  ])
  ->name('*.php')
  ->notPath('/tests/');

$phar->buildFromIterator($finder, __DIR__);
$phar->addFile('vendor/autoload.php');
$phar->addFile('main.php');

$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('main.php'));
$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);

$fileSystem->chmod($pharFile, 0770);
