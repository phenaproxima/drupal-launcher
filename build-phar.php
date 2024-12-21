<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

require_once 'vendor/autoload.php';

$fileSystem = new Filesystem();

$pharFile = 'launcher.phar';
if (file_exists($pharFile)) {
  $fileSystem->remove($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

$finder = Finder::create()
  ->files()
  ->in(__DIR__)
  ->name('*.php')
  ->notName(basename(__FILE__))
  ->notPath('/tests/');

$phar->buildFromIterator($finder, __DIR__);

$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('main.php'));
$phar->stopBuffering();
$phar->compressFiles(Phar::GZ);

$fileSystem->chmod($pharFile, 0770);
