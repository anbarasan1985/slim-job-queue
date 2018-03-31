<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

/*
 * @todo: Move DB config details into config/database.php.
 * For development purpose keeping it here.
 */
class Database{

  // specify your own database credentials
  private $host = "localhost";
  private $db_name = "slim_api";
  private $username = "root";
  private $password = "root";
  public $conn;

  // get the database connection
  public function getConnection(){

    $this->conn = null;

    try{
      $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->conn->exec("set names utf8");
    }catch(PDOException $exception){
      echo "Connection error: " . $exception->getMessage();
    }

    return $this->conn;
  }
}

class JobQueue{

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
        echo '{"error":{"text":' . $e->getMessage() . '}}';
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
        echo '{"error":{"text":' . $e->getMessage() . '}}';
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
      echo '{"error":{"text":'. $e->getMessage() .'}}';
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
      echo '{"error":{"text":'. $e->getMessage() .'}}';
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
      return $this->success($output);
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
      echo '{"error":{"text":'. $e->getMessage() .'}}';
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
      echo '{"error":{"text":'. $e->getMessage() .'}}';
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
    $job_id = $this->sanitize($this->job_id);
    try {
      $connection = $this->conn->prepare($sql);
      $connection->bindParam(":job_id", $job_id,  PDO::PARAM_INT);
      $connection->execute();
      $num = $connection->rowCount();
      if ($num > 0) {
        $output = $connection->fetchAll(PDO::FETCH_OBJ);
      }
      else {
        $output = 'No jobs found';
      }
      return $this->success($output);
    } catch(PDOException $e) {
      echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
  }

  public function getJobStatus() {

  }

  public function getStatisticsByTimespan() {

  }

  /**
   * Format output.
   *
   * @param string $data
   *   Content to encode.
   *
   * @return string
   */
  function success($data) {
    return json_encode(array('status' => 'success', 'data' => $data));
  }

}


function get_jobs_list() {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  // instantiate database and job queue object
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);
  return $jobs_queue->getJobsList();
}

function get_job_info($request) {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  // instantiate database and job queue object
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);
  $jobs_queue->job_id = $request->getAttribute('job_id');
  return $jobs_queue->getJobInfo();
}

function create_job($request) {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  header("Access-Control-Allow-Methods: POST");
  header("Access-Control-Max-Age: 3600");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

  // instantiate database and job queue object
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);

  // get posted data
  $data = json_decode(file_get_contents("php://input"));

  // set job queue property values
  $jobs_queue->submitter_id = $data->submitter_id;
  $jobs_queue->priority = $data->priority;
  $jobs_queue->script = $data->script;
  $jobs_queue->vars = $data->vars;
  $jobs_queue->created = time();
  return $jobs_queue->createJob();
}

function update_job($request) {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  header("Access-Control-Allow-Methods: POST");
  header("Access-Control-Max-Age: 3600");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

  // instantiate database and job queue object
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);

  // get posted data
  $data = json_decode(file_get_contents("php://input"));

  // set job queue property values
  // Job ID and Submitted ID are mandate to update the job.
  $jobs_queue->job_id = $data->job_id;
  $jobs_queue->submitter_id = $data->submitter_id;

  $jobs_queue->priority = $data->priority;
  $jobs_queue->script = $data->script;
  $jobs_queue->vars = $data->vars;
  $jobs_queue->updated = time();
  return $jobs_queue->updateJob();
}

function delete_job() {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  header("Access-Control-Allow-Methods: POST");
  header("Access-Control-Max-Age: 3600");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

  // instantiate database and job queue object
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);

  // get posted data
  $data = json_decode(file_get_contents("php://input"));

  // set job queue property values
  // Job ID and Submitted ID are mandate to update the job.
  $jobs_queue->job_id = $data->job_id;
  $jobs_queue->submitter_id = $data->submitter_id;
  return $jobs_queue->removeJob();
}

function get_job_to_process() {
  // required headers
  // Using allowed origin we can control who can send data, also we can add more
  // security methods to avoid unauthorized usage.
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json; charset=UTF-8");
  // instantiate database and job queue object.
  $database = new Database();
  $db = $database->getConnection();
  // initialize object
  $jobs_queue = new JobQueue($db);
  return $jobs_queue->getJobtoProcess();
}

// Run app
$app->run();
