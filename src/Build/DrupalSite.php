<?php

namespace Adfero\Build;

class DrupalSite extends Build implements Checkoutable, Testable, Backupable {
  protected $install;
  protected $test;
  protected $xml_dir;

  public function __construct(\Adfero\Controller $controller, array $config, $dry_run) {
    parent::__construct($controller,$config,$dry_run);
    $this->install = $config['install'];
    $this->test = $config['test'];
  }

  public function backup() {
    $this->drush(sprintf('sql-dump > "%s"',$this->controller->generateBackupFilePath($this,'sql')));
  }

  public function checkout() {
    $this->execute(sprintf('cd %s && git fetch && git merge origin/%s',$this->getProfilePath(),$this->install['branch']));
  }

  public function install() {
    $this->execute('chmod -R a+w ' . $this->getPath('/sites/default'));
    $this->execute('rm ' . $this->getPath('/sites/default/settings.php'));
    $this->execute('cd ' . $this->getPath() . ' && ' . $this->drush(sprintf('make %s -y',$this->getProfilePath('/'.$this->install['makefile'])),FALSE));
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
    $this->execute('chmod a-w ' . $this->getPath('/sites/default'));
    $this->execute('chmod a-w ' . $this->getPath('/sites/default/settings.php'));
  }

  public function test() {
    $this->drush('en -y simpletest');
    $this->xml_dir = $this->controller->generateTempDirectory();
    $this->execute(sprintf('cd %s && php scripts/run-tests.sh --verbose --php %s --url %s --xml %s %s',$this->getPath(),$this->test['php'],$this->test['url'],$this->xml_dir,$this->test['category']));
  }

  protected function _verifyInstall() {
    $files = $this->getXMLFiles();
    foreach($files as $xml_file) {
      if (!$this->verifyXml($this->xml_dir.'/'.$xml_file)) {
        return false;
      }
    }
    return true;
  }

  public function generateTestResultsHTML() {
    return $this->generateTestHTML();
  }

  private function drush($command,$exec = true) {
    $command = sprintf($this->config['drush_path'].' --root="%s" %s',$this->getPath(),$command);
    if ($exec) {
      $this->execute($command);
    }
    return $command;
  }

  private function getProfilePath($next = '') {
    return $this->getPath('/profiles/'.$this->install['profile'].$next);
  }

  private function generateMergedXMLDocument() {
    $files = $this->getXMLFiles();
    $new_document = new \DOMDocument();
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
    $testsuite = new \DOMDocument();
    $testsuite->loadXML($xml_string);
    $testcases = $testsuite->getElementsByTagName('testcase');
    foreach ($testcases as $testcase) {
      $new_node = $new_document->importNode($testcase,true);
      $new_document->documentElement->appendChild($new_node);
    }
  }

  private function generateTestHTML() {
    $files = scandir($this->xml_dir);
    $sections = array();
    foreach($files as $file) {
      if (strpos($file, '.xml') !== FALSE) {
        $sections[$file] = array(
          'name' => $file,
          'pass' => true,
          'tests' => array()
        );
        $xml_string = file_get_contents($this->xml_dir.'/'.$file);
        $testsuite = new \SimpleXMLElement($xml_string);
        foreach($testsuite->testcase as $testcase) {
          $sections[$file]['name'] = (string)$testcase['classname'];
          $test = array(
            'name' => (string)$testcase['name'],
            'pass' => true,
            'details' => ''
          );
          if ($testcase->failure) {
            $sections[$file]['pass'] = $test['pass'] = false;
            $test['details'] = (string)$testcase->failure;
          }
          $sections[$file]['tests'][(string)$testcase['name']] = $test;
        }
      }
    }

    $html = '';
    foreach($sections as $section) {
      $html .= sprintf('<h2>%s: <span style="color: %s;">%s</span></h2>',$section['name'],$section['pass'] ? 'green' : 'red',$section['pass'] ? 'Pass' : 'Fail');
      $html .= '<table border="1" cellpadding="3" width="100%"><thead><th width="25%">Test</th><th width="25%">Status</th><th width="50%">Details</th></thead><tbody>';
      foreach($section['tests'] as $test) {
        $html .= sprintf('<tr style="background: %s;">',$test['pass'] ? 'green' : 'red');
        $html .= sprintf('<td>%s</td>',$test['name']);
        $html .= sprintf('<td>%s</td>',$test['pass'] ? 'Pass' : 'Fail');
        $html .= sprintf('<td>%s</td>',$test['details']);
        $html .= '</tr>';
      }
      $html .= '</tbody></table>';
    }
    return $html;
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
    $testsuite = new \SimpleXMLElement($xml_string);
    foreach($testsuite as $testcase) {
      if (property_exists($testcase, 'failure')) {
        return false;
      }
    }
    return true;
  }
}