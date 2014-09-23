<?php

namespace Adfero\Build;

class DrupalSite extends Build implements Checkoutable, Testable, Backupable {
  protected $install;
  protected $test;
  protected $xml_dir;

  public function __construct(\Adfero\Controller $controller, array $config) {
    parent::__construct($controller,$config);
    $this->install = $config['install'];
    $this->test = $config['test'];
  }

  public function backup() {
    $this->drush(sprintf('sql-dump > "%s"',$this->controller->generateBackupFilePath($this,'sql')));
  }

  public function checkout() {
    $this->controller->execute(sprintf('cd %s && git fetch && git merge origin/master',$this->getProfilePath()));
  }

  public function install() {
    $this->controller->execute('chmod -R a+w ' . $this->getPath('/sites/default'));
    $this->controller->execute('rm ' . $this->getPath('/sites/default/settings.php'));
    $this->controller->execute('cd ' . $this->getPath() . ' && ' . $this->drush(sprintf('make %s -y',$this->getProfilePath('/'.$this->install['makefile'])),FALSE));
    $this->drush(implode(' ', array(
      'site-install',
      $this->install['profile'],
      '--verbose',
      '-y',
      sprintf('--account-name="%s"',$this->install['user']['name']),
      sprintf('--account-pass="%s"',$this->install['user']['password']),
      sprintf('--account-mail="%s"',$this->install['user']['mail']),
      sprintf('--site-mail="%s"',$this->install['mail']),
      sprintf('--site-name="%s"',$this->getName()),
      sprintf('--db-url="mysql://%s:%s@%s/%s"',$this->install['database']['username'],$this->install['database']['password'],$this->install['database']['host'],$this->install['database']['schema'])
    )));
    $this->controller->execute('chmod a-w ' . $this->getPath('/sites/default'));
    $this->controller->execute('chmod a-w ' . $this->getPath('/sites/default/settings.php'));
  }

  public function test() {
    $this->xml_dir = $this->controller->generateTempDirectory();
    $this->controller->execute(sprintf('cd %s && php scripts/run-tests.sh --verbose --url %s --xml %s %s',$this->getPath(),$this->test['url'],$this->xml_dir,$this->test['category']));
    $xml_file = $this->generateMergedXMLDocument();
    $this->saveArtifact('Test_Results.xml',$xml_file);
  }

  protected function _verifyInstall() {
    $files = xml_files($this->xml_dir);
    foreach($files as $xml_file) {
      if (!$this->verifyXml($this->xml_dir.'/'.$xml_file)) {
        return false;
      }
    }
    return true;
  }

  private function drush($command,$exec = true) {
    $command = sprintf('drush --root="%s" %s',$this->getPath(),$command);
    if ($exec) {
      $this->controller->execute($command);
    }
    return $command;
  }

  private function getProfilePath($next = '') {
    return $this->getPath('/profiles/'.$this->install['profile'].$next);
  }

  private function generateMergedXMLDocument() {
    $files = xml_files($this->xml_dir);
    $new_document = new DOMDocument();
    $new_document->loadXML('<?xml version="1.0"?><testsuite></testsuite>');
    foreach($files as $xml_file) {
      $this->importTestcases($this->xml_dir.'/'.$xml_file,$new_document);
    }
    $new_document->formatOutput = true;
    $xml = $new_document->saveXML();
    file_put_contents($this->xml_dir.'/index.xml', $xml);
    return $this->xml_dir.'/index.xml';
  }

  private function importTestcases($xml_file,$new_document) {
    $xml_string = file_get_contents($xml_file);
    $testsuite = new DOMDocument();
    $testsuite->loadXML($xml_string);
    $testcases = $testsuite->getElementsByTagName('testcase');
    foreach ($testcases as $testcase) {
      $new_node = $new_document->importNode($testcase,true);
      $new_document->documentElement->appendChild($new_node);
    }
  }

  private function getXMLFiles() {
    $xml_files = array();
    $files = scandir($this->xml_dir);
    foreach($files as $xml_file) {
      if (strpos($xml_file,'.xml') !== FALSE) {
        $xml_files[] = $xml_file;
      }
    }
    return $xml_files;
  }

  private function verifyXml($file) {
    $xml_string = file_get_contents($file);
    $testsuite = new SimpleXMLElement($xml_string);
    foreach($testsuite as $testcase) {
      if (property_exists($testcase, 'failure')) {
        return false;
      }
    }
    return true;
  }
}