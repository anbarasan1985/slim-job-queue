<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

// API group
$app->group('/api', function () use ($app) {
  // Version group
  $app->group('/v1', function () use ($app) {
    $this->get('/jobs', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      // initialize object
      $JobQueueController = $this->get("App\Controllers\JobQueueController");
      return $JobQueueController->getJobsList();
    });
    $this->get('/job/{job_id:[0-9]+}', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      // initialize object
      $JobQueueController = $this->get("App\Controllers\JobQueueController");
      $JobQueueController->job_id = $request->getAttribute('job_id');
      return $JobQueueController->getJobInfo();
    });
    $this->get('/get_job', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      // get posted data
      $data = json_decode(file_get_contents("php://input"));
      $JobQueueController = $this->get("App\Controllers\JobQueueController");
      // initialize object
      $JobQueueController->processor_id = $data->processor_id;
      return $JobQueueController->getJobtoProcess();
    });
    $this->post('/job/create', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      header("Access-Control-Allow-Methods: POST");
      header("Access-Control-Max-Age: 3600");
      header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
      // initialize object
      $JobQueueController = $this->get("App\Controllers\JobQueueController");

      // get posted data
      $data = json_decode(file_get_contents("php://input"));

      // set job queue property values
      $JobQueueController->submitter_id = $data->submitter_id;
      $JobQueueController->priority = $data->priority;
      $JobQueueController->script = $data->script;
      $JobQueueController->vars = $data->vars;
      $JobQueueController->created = time();
      return $JobQueueController->createJob();
    });
    $this->put('/job/update/{id:[0-9]+}', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      header("Access-Control-Allow-Methods: POST");
      header("Access-Control-Max-Age: 3600");
      header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
      // initialize object
      $JobQueueController = $this->get("App\Controllers\JobQueueController");

      // get posted data
      $data = json_decode(file_get_contents("php://input"));

      // set job queue property values
      // Job ID and Submitted ID are mandate to update the job.
      $JobQueueController->job_id = $data->job_id;
      $JobQueueController->submitter_id = $data->submitter_id;

      $JobQueueController->priority = $data->priority;
      $JobQueueController->script = $data->script;
      $JobQueueController->vars = $data->vars;
      $JobQueueController->updated = time();
      return $JobQueueController->updateJob();
    });
    $this->delete('/job/delete', function ($request, $response, array $args) {
      // required headers
      // Using allowed origin we can control who can send data, also we can add more
      // security methods to avoid unauthorized usage.
      header("Access-Control-Allow-Origin: *");
      header("Content-Type: application/json; charset=UTF-8");
      header("Access-Control-Allow-Methods: POST");
      header("Access-Control-Max-Age: 3600");
      header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

      // get posted data
      $data = json_decode(file_get_contents("php://input"));
      $JobQueueController = $this->get("App\Controllers\JobQueueController");
      // set job queue property values
      // Job ID and Submitted ID are mandate to update the job.
      $JobQueueController->job_id = $data->job_id;
      $JobQueueController->submitter_id = $data->submitter_id;
      return $JobQueueController->removeJob($request);
    });
  });
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});