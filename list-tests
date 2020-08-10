#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \Testomatio\Command());
$application->setDefaultCommand('check-tests', true);
$application->run();