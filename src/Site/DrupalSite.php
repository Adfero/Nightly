<?php

namespace Adfero\Site;

class DrupalSite extends Site implements Testable {
  protected $install;
  protected $test;

  public function __construct(\Adfero\Controller $controller, array $config) {
    parent::__construct($controller,$config);
    $this->install = $config['install'];
    $this->test = $config['test'];
  }

  private function drush($command,$exec = true) {
    $command = sprintf('drush --root="%s" %s',$this->getPath(),$command);
    if ($exec) {
      $this->controller->execute($command);
    }
    return $command;
  }

  public function backup() {
    $this->drush(sprintf('sql-dump > "%s"',$this->getBackupFile()));
  }

  public function checkout() {
    $this->controller->execute(sprintf('cd %s && git fetch && git merge origin/master',$this->getPath()));
  }

  public function install() {
    $this->controller->execute('chmod -R a+w ' . $this->getPath('/sites/default'));
    $this->controller->execute('rm ' . $this->getPath('/sites/default/settings.php'));
    $this->controller->execute('cd ' . $this->getPath() . ' && ' . $this->drush(sprintf('make %s -y',$this->getPath('/profiles/'.$this->install['profile'].'/'.$this->install['makefile'])),FALSE));
    $this->drush(implode(' ', array(
      'site-install',
      $this->install['profile'],
      '--verbose',
      sprintf('--account-name="%s"',$this->install['user']['name']),
      sprintf('--account-pass="%s"',$this->install['user']['password']),
      sprintf('--account-mail="%s"',$this->install['user']['mail']),
      sprintf('--site-mail="%s"',$this->install['mail']),
      sprintf('--site-name="%s"',$this->getName()),
      sprintf('--db-url="mysql://%s:%s@%s/%s',$this->install['database']['username'],$this->install['database']['password'],$this->install['database']['host'],$this->install['database']['schema'])
    )));
    $this->controller->execute('chmod a-w ' . $this->getPath('/sites/default'));
    $this->controller->execute('chmod a-w ' . $this->getPath('/sites/default/settings.php'));
  }

  public function test() {
    //TODO
  }
}