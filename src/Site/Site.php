<?php

namespace Adfero\Site;

abstract class Site {
  protected $controller;
  protected $name;
  protected $path;

  public function __construct(\Adfero\Controller $controller, array $config) {
    $this->controller = $controller;
    $this->name = $config['name'];
    $this->path = $config['path'];
  }

  public function getName() {
    return $this->name;
  }

  public function getPath($next = '') {
    return $this->path . $next;
  }

  public function getBackupFile() {
    return 'backup.sql'; //TODO
  }


  public function run() {
    $this->backup();
    $this->checkout();
    $this->install();
  }
  
  public abstract function backup();
  public abstract function checkout();
  public abstract function install();
}