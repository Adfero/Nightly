<?php

require 'vendor/autoload.php';

$commands = new Commando\Command();

$commands->option('c')
  ->aka('config')
  ->describedAs('Path to config.json');

$commands->option('d')
  ->aka('dryrun')
  ->describedAs('Dry run')
  ->boolean();

$controller = new Adfero\Controller(empty($commands['config']) ? './config.json' : $commands['config'], $commands['dryrun'] == 1);
$controller->validateAndLoadSettings();
$controller->run();