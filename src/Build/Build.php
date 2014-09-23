<?php

namespace Adfero\Build;

abstract class Build {
  protected $controller;
  protected $slug;
  protected $name;
  protected $path;
  protected $successful_build;
  private $artifacts;

  public function __construct(\Adfero\Controller $controller, array $config) {
    $this->controller = $controller;
    $this->slug = $config['slug'];
    $this->name = $config['name'];
    $this->path = $config['path'];
    $this->artifacts = array();
    $this->successful_build = true;
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
    $email->Subject = sprintf('%@ Build Results',$this->name);
    $email->Body = sprintf('<h1>%@ Build Results</h1>',$this->name);
    $email->body .= sprintf('<p>Status: <strong style="color: %s;">%s</strong></p>',$this->successful_build ? 'green' : 'red',$this->successful_build ? 'Pass' : 'Fail');
    foreach($this->artifacts as $name => $file) {
      $email->addAttachment($file,$name);
    }
  }
  
  public abstract function install();
  protected abstract function _verifyInstall();
}