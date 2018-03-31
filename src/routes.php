<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

// API group
$app->group('/api', function () use ($app) {
  // Version group
  $app->group('/v1', function () use ($app) {
    $app->get('/jobs', 'get_jobs_list');
    $app->get('/job/{job_id}', 'get_job_info');
    $app->get('/get_job', 'get_job_to_process');
    $app->post('/create', 'create_job');
    $app->put('/update/{id}', 'update_job');
    $app->delete('/delete/{id}', 'delete_job');
  });
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});