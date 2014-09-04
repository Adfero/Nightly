<?php

namespace Adfero;

class Controller {
  private $config_file_path = false;
  private $dry_run;
  private $config;

  public function __construct($config = './config.json', $dry_run = FALSE) {
    if (is_array($config)) {
      $this->config_file_path = false;
      $this->config = $config;
    } else {
      $this->config_file_path = $config;
      $this->config = array();
    }
    $this->dry_run = $dry_run;
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

  public function run() {
    foreach($this->config['sites'] as $site_config) {
      $site = new $site_config['type']($this,$site_config);
      if ($site && $site instanceof \Adfero\Site\Site) {
        $site->run();
      }
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
      $this->log(exec($command));
    }
  }
}