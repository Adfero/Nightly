<?php

namespace Adfero\Build;

abstract class Build {
  protected $controller;
  protected $slug;
  protected $name;
  protected $path;
  protected $successful_build;
  private $artifacts;
  private $log;
  private $dry_run;

  public function __construct(\Adfero\Controller $controller, array $config, $dry_run) {
    $this->controller = $controller;
    $this->slug = $config['slug'];
    $this->name = $config['name'];
    $this->path = $config['path'];
    $this->artifacts = array();
    $this->successful_build = true;
    $this->log = '';
    $this->dry_run = $dry_run;
  }

  public function run() {
    if ($this instanceof \Adfero\Build\Backupable) {
      $this->backup();
    }
    if ($this instanceof \Adfero\Build\Checkoutable) {
      $this->checkout();
    }
    $this->install();
    if ($this instanceof \Adfero\Build\Testable) {
      $this->test();
    }
    $this->verifyInstall();
  }

  public function getName() {
    return $this->name;
  }

  public function getSlug() {
    return $this->slug;
  }

  public function setSuccessfulBuild($s) {
    $this->successful_build = $s;
  }

  public function getSuccessfulBuild() {
    return $this->successful_build;
  }

  public function getPath($next = '') {
    return $this->path . $next;
  }

  public function saveArtifact($name,$file) {
    $this->artifacts[$name] = $file;
  }

  public function verifyInstall() {
    $this->successful_build = $this->_verifyInstall();
  }

  public function constructEmail(\PHPMailer $email) {
    $email->Subject = sprintf('%s Build Results',$this->name);
    $email->Body = sprintf('<h1>%s Build Results</h1>',$this->name);
    $email->Body .= sprintf('<p>Status: <strong style="color: %s;">%s</strong></p>',$this->successful_build ? 'green' : 'red',$this->successful_build ? 'Pass' : 'Fail');
    if ($this instanceof Testable) {
      $email->Body .= $this->generateTestResultsHTML();
    }
    foreach($this->artifacts as $name => $file) {
      $email->addAttachment($file,$name);
    }
    $logfile = $this->controller->generateTempFile('log');
    file_put_contents($logfile, $this->log);
    $email->addAttachment($logfile,'build.log');
  }

  protected function log($message) {
    $this->controller->log($message);
    $this->log .= $message . "\n";
  }

  protected function execute($command) {
    if ($this->dry_run) {
      $this->log("Dry Execute: " . $command);
    } else {
      $this->log("Execute: " . $command);
      $data = array();
      $return_var = 0;
      exec($command,$data,$return_var);
      foreach($data as $out) {
        $this->log($out);
      }
      if ($return_var != 0) {
        $this->log('Command failed with error: '.$return_var);
      }
    }
  }
  
  public abstract function install();
  protected abstract function _verifyInstall();
}