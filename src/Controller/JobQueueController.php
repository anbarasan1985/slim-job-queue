<?php

namespace App\Controllers;

use PDO;
/**
 * Class JobQueue.
 */
class JobQueueController {

  // database connection and table name
  private $conn;
  private $table_name = "job_queue";
  // object properties
  // Job id auto increment value.
  public $job_id;
  // Submitter id based on who is submitting the job.
  public $submitter_id;
  // Job priority, lower value has more priority.
  public $priority;
  // Job script to execute.
  public $script;
  // Variables passed to the job script.
  public $vars;
  // Processor ID in case if Job is processed.
  public $processor_id;
  // When was the job ran at last.
  public $last_run;
  // When was job created.
  public $created;
  // When was job updated.
  public $updated;
  public $request;
  // Path to cache folder (with trailing /)
  public $cache_path = 'cache/';
  // Length of time to cache a file (in seconds)
  public $cache_time = 60;
  // Cache file extension
  public $cache_extension = '.cache';

  // constructor with $db as database connection
  public function __construct($db){
    $this->conn = $db;

  }

  /**
   * Set cache content.
   *
   * @param string $label
   *   cache label.
   * @param string $data
   *   data to store in cache.
   */
  public function set_cache($label, $data) {
    // Storing to files, but we can put it in cache bins. (Memcache / Redis)
    file_put_contents($this->cache_path . $this->safe_filename($label) . $this->cache_extension, $data);
  }

  /**
   * Get cache content.
   *
   * @param string $label
   *   cache label.
   *
   * @return string/boolean
   */
  public function get_cache($label) {
    if($this->is_cached($label)){
      $filename = $this->cache_path . $this->safe_filename($label) . $this->cache_extension;
      return file_get_contents($filename);
    }
    return false;
  }

  /**
   * Check whether content exist in cache.
   *
   * @param string $label
   *   cache label.
   *
   * @return boolean
   */
  public function is_cached($label) {
    $filename = $this->cache_path . $this->safe_filename($label) . $this->cache_extension;
    if(file_exists($filename) && (filemtime($filename) + $this->cache_time >= time())) {
      return true;
    }
    else {
      return false;
    }
  }

  /**
   * Helper function to validate file names.
   *
   * @param string $filename
   *   File name to convert.
   *
   * @return string
   */
  private function safe_filename($filename) {
    return preg_replace('/[^0-9a-z\.\_\-]/i','', strtolower($filename));
  }

  /**
   * Set request value.
   *
   * @param string $request
   */
  public function setRequest($request) {
    $this->request = $request;
  }

  /**
   * Helper function to sanitize.
   *
   * @param string $input
   *   Input value to sanitize.
   *
   * @return string
   */
  public function sanitize($input) {
    return htmlspecialchars(strip_tags($input));
  }

  /**
   * Get all list of jobs.
   *
   * @return string
   */
  public function getJobsList() {
    $label = 'alljobs' . __FUNCTION__;

    if ($output = $this->get_cache($label)) {
      return $this->success($output);
    }
    else {
      $sql = "SELECT * FROM " . $this->table_name;
      try {
        $connection = $this->conn->query($sql);
        $num = $connection->rowCount();
        if ($num > 0) {
          $output = $connection->fetchAll(PDO::FETCH_OBJ);
          $this->set_cache($label, $output['data']);
        } else {
          $output = 'No results found';
        }
        return $this->success($output);
      } catch (PDOException $e) {
        return $this->error($e->getMessage());
      }
    }
  }

  /**
   * Get single job info.
   *
   * @return string
   */
  public function getJobInfo() {
    $label = 'singlejob' . __FUNCTION__ . $this->job_id;
    if ($output = $this->get_cache($label)) {
      return $this->success($output);
    }
    else {
      $sql = "SELECT * FROM " . $this->table_name . " WHERE job_id=:job_id";
      $job_id = $this->sanitize($this->job_id);
      try {
        $connection = $this->conn->prepare($sql);
        $connection->bindParam(":job_id", $job_id, PDO::PARAM_INT);
        $connection->execute();
        $num = $connection->rowCount();
        if ($num > 0) {
          $output = $connection->fetchAll(PDO::FETCH_OBJ);
          $this->set_cache($label, $output['data']);
        } else {
          $output = 'No results found';
        }
        return $this->success($output);
      } catch (PDOException $e) {
        return $this->error($e->getMessage());
      }
    }
  }

  /**
   * Check job exist in the queue.
   *
   * @return boolean/string
   */
  public function jobExist() {
    $sql = "SELECT * FROM " . $this->table_name . " WHERE job_id=:job_id AND submitter_id = :submitter_id";
    try {
      $connection = $this->conn->prepare($sql);
      $connection->bindParam(":job_id", $this->sanitize($this->job_id),  PDO::PARAM_INT);
      $connection->bindParam(":submitter_id", $this->sanitize($this->job_id),  PDO::PARAM_STR);
      $connection->execute();
      $num = $connection->rowCount();
      if ($num > 0) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * Create job.
   *
   * @return string
   */
  public function createJob() {
    $sql = "INSERT INTO " . $this->table_name . "  (submitter_id, priority, script, vars, created) VALUES (:submitter_id, :priority, :script, :vars, :created)";
    try {
      $connection = $this->conn->prepare($sql);
      $connection->bindParam(":submitter_id", $this->sanitize($this->submitter_id), PDO::PARAM_STR);
      $connection->bindParam(":priority", $this->sanitize($this->priority), PDO::PARAM_STR);
      $connection->bindParam(":script", $this->sanitize($this->script), PDO::PARAM_STR);
      $connection->bindParam(":vars", $this->sanitize($this->vars), PDO::PARAM_STR);
      $connection->bindParam(":created", $this->created, PDO::PARAM_STR);
      $connection->execute();
      $output['id'] = $this->id = $this->conn->lastInsertId();
      return $this->success($output);
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * Update job.
   *
   * @return string
   */
  public function updateJob() {
    if ($this->jobExist() !== TRUE) {
      $output = 'Job from Queue not found';
      return $this->error($output);
    }
    $sql = "UPDATE " . $this->table_name . "  SET priority = :priority, script = :script, vars = :vars) WHERE job_id = :job_id AND submitter_id = :submitter_id";
    try {
      $qry = $this->conn->prepare($sql);
      $qry->bindParam(":priority", $this->sanitize($this->priority), PDO::PARAM_STR);
      $qry->bindParam(":script", $this->sanitize($this->script), PDO::PARAM_STR);
      $qry->bindParam(":vars", $this->sanitize($this->vars), PDO::PARAM_STR);
      $qry->bindParam(":job_id", $this->sanitize($this->job_id),  PDO::PARAM_INT);
      $qry->bindParam(":submitter_id", $this->sanitize($this->submitter_id), PDO::PARAM_STR);
      $qry->execute();
      $output = 'Job from Queue updated successfully';
      return $this->success($output);
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * Remove job.
   *
   * @return string
   */
  public function removeJob() {
    if ($this->jobExist() !== TRUE) {
      $output = 'Job from Queue not found';
      return $this->success($output);
    }
    $sql = "DELETE FROM " . $this->table_name . "  WHERE job_id=:job_id and submitter_id = :submitter_id";
    try {
      $connection = $this->conn->prepare($sql);
      $connection->bindParam(":job_id", $this->sanitize($this->job_id),  PDO::PARAM_INT);
      $connection->bindParam(":submitter_id", $this->sanitize($this->submitter_id), PDO::PARAM_STR);
      $connection->execute();
      $output = 'Job from Queue deleted successfully';
      return $this->success($output);
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * Get top priority job.
   *
   * @return string
   */
  public function getJobtoProcess() {
    // We need to get only one job based on the top priority. Also make sure it
    // is not processed already. (i.e, processor_id empty means it is not
    // processed already. Also sort by priority
    $sql = "SELECT * FROM " . $this->table_name . " WHERE processor_id = '' ORDER BY priority ASC LIMIT 1";
    try {
      $connection = $this->conn->prepare($sql);
      $connection->execute();
      $num = $connection->rowCount();
      if ($num > 0) {
        $output = $connection->fetchAll(PDO::FETCH_OBJ);
        // Update the picked up JOB so no one else can pick up the same job.
        $update_sql = "UPDATE " . $this->table_name . "  SET processor_id = :processor_id WHERE job_id = :job_id";
        $qry = $this->conn->prepare($update_sql);
        $qry->bindParam(":processor_id", $this->sanitize($this->processor_id), PDO::PARAM_STR);
        $qry->bindParam(":job_id", $output->job_id,  PDO::PARAM_INT);
        //$qry->execute();
      }
      else {
        $output = 'No jobs found';
      }
      return $this->success($output);
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * Get job status (Pending/Finished).
   *
   * @return string
   */
  public function getJobStatus() {
    if ($this->jobExist() !== TRUE) {
      $output = 'Job from Queue not found';
      return $this->success($output);
    }
    $sql = "SELECT processor_id FROM " . $this->table_name . "  WHERE job_id=:job_id and submitter_id = :submitter_id";
    try {
      $connection = $this->conn->prepare($sql);
      $connection->bindParam(":job_id", $this->sanitize($this->job_id),  PDO::PARAM_INT);
      $connection->bindParam(":submitter_id", $this->sanitize($this->submitter_id), PDO::PARAM_STR);
      $connection->execute();
      $num = $connection->rowCount();
      if ($num > 0) {
        $result = $connection->fetchAll(PDO::FETCH_OBJ);
        $output = ($result->processor_id) ? 'Finished' : 'Pending';
      }
      return $this->success($output);
    } catch(PDOException $e) {
      return $this->error($e->getMessage());
    }
  }

  /**
   * @param array $request_data
   *
   * @return int (last inserted id)
   */
  public function add($request_data) {
    $table = $this->table_name;
    if ($request_data == null) {
      return false;
    }
    $columnString = implode(',', array_flip($request_data));
    $valueString = ":".implode(',:', array_flip($request_data));
    $sql = "INSERT INTO " . $table . " (" . $columnString . ") VALUES (" . $valueString . ")";
    $connection = $this->pdo->prepare($sql);
    foreach($request_data as $key => $value){
      $connection->bindValue(':' . $key,$request_data[$key]);
    }
    $connection->execute();
    return $this->pdo->lastInsertId();
  }
  /**
   * @param array $request_data
   *
   * @return bool
   */
  public function update($request_data) {
    $table = $this->table_name;
    // if no data to update or not key set = return false
    if ($request_data == null || !isset($args[implode(',', array_flip($args))])) {
      return false;
    }
    $sets = 'SET ';
    foreach($request_data as $key => $value){
      $sets = $sets . $key . ' = :' . $key . ', ';
    }
    $sets = rtrim($sets, ", ");
    $sql = "UPDATE ". $table . ' ' . $sets . ' WHERE ' . implode(',', array_flip($args)) . ' = :' . implode(',', array_flip($args));

    $connection = $this->pdo->prepare($sql);
    foreach($request_data as $key => $value){
      $connection->bindValue(':' . $key,$request_data[$key]);
    }

    // bind the key
    $connection->bindValue(':' . implode(',', array_flip($args)), implode(',', $args));
    $connection->execute();
    return ($connection->rowCount() == 1) ? true : false;
  }

  public function getByTimespan() {
    // Find out based on Job start time and End time.
  }

  /**
   * Format success output.
   *
   * @param string $data
   *   Content to encode.
   *
   * @return string
   */
  function success($data) {
    return json_encode(array('status' => 'success', 'data' => $data));
  }

  /**
   * Format error output.
   *
   * @param string $data
   *   Content to encode.
   *
   * @return string
   */
  function error($data) {
    return json_encode(array('status' => 'error', 'data' => $data));
  }

  /**
   * Set a default response code if the request can't be processed.
   *
   * @param string $message
   */
  function bad_request($message = '400 Bad Request') {
    api_error('HTTP/1.1 400 Bad Request', 400, $message);
  }

  /**
   * Return a default access denied response.
   */
  function access_denied($message = '403 Access Denied') {
    api_error('HTTP/1.1 403 Access Denied', 403, $message);
  }

  /**
   *
   */
  function server_error($message = '500 Server Error') {
    api_error('HTTP/1.1 500 Server Error', 500, $message);
  }

  /**
   * Return a default access denied response.
   */
  function not_found() {
    api_error('HTTP/1.1 404 Not Found', 404, '404 Not Found');
  }
  /**
   *
   */
  function api_error($header, $code, $message) {
    header($header, TRUE, $code);
    json_encode(array('status' => 'error', 'message' => $message));
  }
}
