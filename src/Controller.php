<?php

namespace Adfero;

class Controller {
  private $config_file_path = false;
  private $dry_run;
  private $config;
  private $temp_files;

  public function __construct($config = './config.json', $dry_run = FALSE) {
    if (is_array($config)) {
      $this->config_file_path = false;
      $this->config = $config;
    } else {
      $this->config_file_path = $config;
      $this->config = array();
    }
    $this->dry_run = $dry_run;
    $this->temp_files = array();
  }

  public function validateAndLoadSettings() {
    if ($this->config_file_path !== FALSE && !file_exists($this->config_file_path)) {
      throw new \Exception(sprintf('The file "%s" does not exist.',$this->config_file_path));
    }

    $contents = file_get_contents($this->config_file_path);
    $this->config = json_decode($contents,true);

    if (is_array($this->config)) {
      // if (!(isset($this->config['smpt']) 
      //   && isset($this->config['smpt']['username']) 
      //   && isset($this->config['smpt']['password']) 
      //   && isset($this->config['smpt']['from']) 
      //   && is_array($this->config['smpt']['from'])
      //   && isset($this->config['smpt']['from']['address']) 
      //   && isset($this->config['smpt']['from']['name'])
      //   && isset($this->config['smpt']['to'])
      //   && is_array($this->config['smpt']['to'])))
      // {
      //   throw new \Exception('Invalid SMTP settings.');
      // }


    } else {
      throw new \Exception('The config provided is not an array.');
    }
    
    return TRUE;
  }

  public function generateTempFile($extension = 'tmp') {
    if (isset($this->config['tmp_dir'])) {
      $tmp_dir = $this->config['tmp_dir']; 
    } else {
      $tmp_dir = '/tmp';
    }
    $name = md5(time().'-'.rand());
    $path = sprintf('%s/%s.%s',$tmp_dir,$name,$extension);
    $this->temp_files[] = $path
    return $path;
  }

  public function generateTempDirectory() {
    if (isset($this->config['tmp_dir'])) {
      $tmp_dir = $this->config['tmp_dir']; 
    } else {
      $tmp_dir = '/tmp';
    }
    $name = md5(time().'-'.rand());
    $path = sprintf('%s/%s',$tmp_dir,$name);
    mkdir($path);
    return $path;
  }

  public function generateBackupFilePath(\Adfero\Build\Build $build,$extension = 'bak') {
    if (isset($this->config['backup_dir'])) {
      $bckup_dir = $this->config['backup_dir']; 
    } else {
      $bckup_dir = '/tmp';
    }
    return sprintf('%s/%s-%l.%s',$bckup_dir,$build->getSlug(),time(),$extension);
  }

  public function run() {
    foreach($this->config['builds'] as $build_config) {
      $build = new $build_config['type']($this,$build_config);
      if ($build && $build instanceof \Adfero\Build\Build) {
        $this->executeBuild($build);
        $this->emailBuild($build);
      }
    }
  }

  private function executeBuild(\Adfero\Build\Build $build) {
    if ($build instanceof \Adfero\Build\Backupable) {
      $build->backup();
    }
    if ($build instanceof \Adfero\Build\Checkoutable) {
      $build->checkout();
    }
    $build->install();
    if ($build instanceof \Adfero\Build\Testable) {
      $build->test();
    }
    $build->verifyInstall();
  }

  private function emailBuild(\Adfero\Build\Build $build) {
    $mail = new \PHPMailer;
    $mail->isSMTP(); 
    $mail->Host = $this->config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $this->config['smtp']['username'];
    $mail->Password = $this->config['smtp']['password'];
    $mail->SMTPSecure = 'tls'; 
    $mail->From = $this->config['smtp']['from']['address'];
    $mail->FromName = $this->config['smtp']['from']['name'];
    foreach($this->config['smtp']['to'] as $address) {
      $mail->addAddress($address);
    }
    $mail->isHTML(true);
    $build->constructEmail($mail);
    if (!$mail->send()) {
      $this->log('Mailer Error: ' . $mail->ErrorInfo);
    }
  }

  public function log($message) {
    echo $message."\n";
  }

  public function execute($command) {
    if ($this->dry_run) {
      $this->log("Dry Execute: " . $command);
    } else {
      $this->log("Execute: " . $command);
      $data = array();
      exec($command,$data);
      foreach($data as $out) {
        $this->log($out);
      }
    }
  }
}