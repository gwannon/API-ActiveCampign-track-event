<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

ini_set("display_errors", 1);

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../app/config.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();

//custom Error Handler
$customErrorHandler = function (
  Request $request,
  Throwable $exception
) use ($app) {
  $body['status'] = 'Error';
  $body['message'] = $exception->getMessage();
  $response = $app->getResponseFactory()->createResponse();
  $response->getBody()->write(
    json_encode($body, JSON_UNESCAPED_UNICODE)
  );
  $response = $response->withStatus(400);
  return $response;
};

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, false, false);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);


$app->run();
