<?php

namespace DrushInvoker;

/**
 * Defines from Drush.
 */
define('DRUSH_BACKEND_OUTPUT_START', 'DRUSH_BACKEND_OUTPUT_START>>>');
define('DRUSH_BACKEND_OUTPUT_DELIMITER', DRUSH_BACKEND_OUTPUT_START . '%s<<<DRUSH_BACKEND_OUTPUT_END');

/**
 * Run drush and parse the output.
 */
class DrushInvoker {
  private $alias = null;
  private $result = null;

  public function __construct($alias) {
    $this->alias = $alias;
  }

  /**
   * Factory.
   */
  public static function invoke($alias, $command, $options = array(), $args = array()) {
    $instance = new DrushInvoker($alias);
    return $instance->call($command, $options, $args);
  }

  /**
   * Call a drush command.
   */
  public function call($command, $options = array(), $args = array()) {
    // Reset previous command.
    $this->result = null;

    $cmd = array();
    $cmd[] = 'drush';
    $cmd[] = $this->alias;
    $cmd[] = $command;
    $cmd[] = '--backend --quiet';
    foreach ($options as $option) {
      $cmd[] = escapeshellarg($option);
    }
    foreach ($args as $arg) {
      $cmd[] = escapeshellarg($arg);
    }
    $cmd = implode(' ', $cmd);

    $output = '';
    $retval = $this->exec($cmd, $output);
    if ($retval > 0) {
      throw new DrushException('Error running ' . $cmd);
    }
    else {
      $this->result = $this->parseDrushOutput($output);
    }

    return $this;
  }

  /**
   * Execute bash command using proc_open().
   *
   * With inspiration from drush_shell_proc_open().
   */
  private function exec($cmd, &$output) {
    $descriptorspec = array(
      0 => STDIN,
      1 => array('pipe', 'w'),
      2 => array('file', '/dev/null', 'w'),
    );

    $process = proc_open($cmd, $descriptorspec, $pipes);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $proc_status = proc_get_status($process);
    $exit_code = proc_close($process);
    $retval = ($proc_status["running"] ? $exit_code : $proc_status["exitcode"] );

    return $retval;
  }

  /**
   * Returns the output of the last command run.
   */
  public function getOutput() {
    if ($this->result) {
      return $this->result['object'];
    }
  }

  /**
   * Returns the log of the last command run.
   */
  public function getLog($debug = FALSE) {
    $logs = array();
    if ($this->result) {
      foreach ($this->result['log'] as $log) {
        if ($debug || !in_array($log['type'], array('preflight', 'bootstrap', 'debug', 'notice'))) {
          $logs[] = $log;
        }
      }
    }
    return $logs;
  }

  /**
   * Parse output returned from a Drush command.
   *
   * Simplified version of drush_backend_parse_output().
   *
   * @param string
   *    The output of a drush command
   *
   * @return
   *   An associative array containing the data from the external command, or the string parameter if it
   *   could not be parsed successfully.
   */
  function parseDrushOutput($string) {
    $regex = sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, '(.*)');

    preg_match("/$regex/s", $string, $match);

    if (!empty($match) && $match[1]) {
      // we have our JSON encoded string
      $output = $match[1];
      // remove the match we just made and any non printing characters
      $string = trim(str_replace(sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, $match[1]), '', $string));
    }

    if (!empty($output)) {
      return json_decode($output, TRUE);
    }

    return $string;
  }
}

