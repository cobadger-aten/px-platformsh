<?php

declare(strict_types=1);

namespace  Pr0jectX\PxPlatformsh;

use Robo\Collection\CollectionBuilder;

class CommandOutputReader {

  protected $task;

  protected $parsed;

  public function __construct(CollectionBuilder $task)
  {
    $this->task = $task;
  }

  public static function init(CollectionBuilder $task)
  {
    return new static($task);
  }

  /**
   * Create the array from the task output.
   */
  private function parsed()
  {
      if (!isset($this->parsed)) {
          $output = $this->task->option('format', 'csv')
            ->printOutput(false)
            ->silent(true)
            ->run();

          $csv = $output->getMessage();
          // TODO: Make this a setting.
          $this->parsed = $this->csvToArray($csv, 2);
      }
      return $this->parsed;
  }

  /**
   * Get a row value by keyed ID.
   */
  public function get($name)
  {
      $parsed = $this->parsed();
      $value = $parsed[$name] ?? NULL;
      return $value;
  }

  /**
   * TODO: Make this a public function.
   */
  private function csvStringToOptions($csv_string, $key)
  {
      $lines = explode(PHP_EOL, $csv_string);
      $csv_parsed = array_map('str_getcsv', $lines);
      $header = array_shift($csv_parsed);

      return array_reduce($csv_parsed, function($carry, $item) use ($header, $key) {
          $arr = array_combine($header, $item);
          $carry[$arr[$key]] = $arr[$key];
          return $carry;
      }, []);
  }

  /**
   * Convert csv string to array.
   */
  private function csvToArray($csv_string, $column_count)
  {
      $lines = explode(PHP_EOL, $csv_string);
      $csv_parsed = array_map('str_getcsv', $lines);

      $header = array_shift($csv_parsed);
      $header_key = array_shift($header);
      $info_array = [];
      foreach ($csv_parsed as $row) {
          $key = array_shift($row);
          $row = array_pad($row, count($header), '');

          $info_array[$key] = count($row) == 1 ? reset($row) : array_combine($header, $row);
      }

      return $info_array;
  }
}
