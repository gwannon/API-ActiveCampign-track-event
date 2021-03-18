<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Exception\NotFoundException;

require __DIR__ . '/../vendor/autoload.php';

define('AC_API_DOMAIN', 'XXXXXXXXX'); 
define('AC_API_TOKEN', 'XXXXXXXXX');
define('AC_API_TRACKING_DOMAIN', 'https://trackcmp.net/event'); 
define('AC_API_TRACKING_ID', 'XXXXXXXXX');
define('AC_API_TRACKING_TOKEN', 'XXXXXXXXX');

$app = AppFactory::create();

$app->addRoutingMiddleware();

//custom Error Handler
$customErrorHandler = function (
  Request $request,
  Throwable $exception/* ,
  bool $displayErrorDetails,
  bool $logErrors,
  bool $logErrorDetails */
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

//CORS -> aceptamos todas las peticiones OPTIONS para superar el CORS
$app->options('/{routes:.+}', function ($request, $response, $args) {
  return $response;
  /*return $response->withHeader('Access-Control-Allow-Origin', '*')  
  ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
  ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');*/
});

$app->get('/event/all', function (Request $request, Response $response, array $args) {
  if(!checkAllowedReferer ()) {
    $response = responseError($response, 'Not allowed referer', $args, 400);
    return $response;    
  }

	$events = getAllAllowedEvents ();
    $response->getBody()->write(json_encode($events));
	return $response;
});

$app->put('/event/{event_name:[a-z0-9\-]+}/{contact_id:[0-9]+}', function (Request $request, Response $response, array $args) use ($app) {
  if(!checkAllowedReferer ()) {
    $response = responseError($response, 'Not allowed referer', $args, 400);
    return $response;    
  }

  $allowed_events = getAllAllowedEvents ();

  if(!in_array($args['event_name'], $allowed_events)) { //NO es uno de los eventos registrados
    $response = responseError($response, 'Not allowed {event name}', $args, 400);
    return $response;
  }

  $email = getActiveCammpignEmailByContactId ($args['contact_id']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {  //NO existe el usuario
    $response = responseError($response, '{contact_id} not exist', $args, 400);
    return $response;
  }

  $curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, AC_API_TRACKING_DOMAIN);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, array(
      "actid" => AC_API_TRACKING_ID,
      "key" => AC_API_TRACKING_TOKEN,
      "event" => $args['event_name'],
      "visit" => json_encode(array(
			"email" => $email,
		)),
	));

	$result = curl_exec($curl);
	if ($result !== false) {
		$result = json_decode($result);
		if ($result->success) {
			$body['status'] = 'OK';
		} else {
      $response = responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
		}
	} else {
    $response = responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
	}

  $body['args'] = $args;
  $response->getBody()->write(json_encode($body));

  return $response;
});

$app->get('/contact/email/{contact_id:[0-9]+}', function (Request $request, Response $response, array $args) {
  if(!checkAllowedReferer ()) {
    $response = responseError($response, 'Not allowed referer', $args, 400);
    return $response;    
  }

	$email = getActiveCammpignEmailByContactId ($args['contact_id']);
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {  //NO existe el usuario
    $response = responseError($response, '{contact_id} not exist', $args, 400);
    return $response;
  } else $response->getBody()->write(json_encode(array("email" => $email)));
	return $response;
});

$app->get('/site/all', function (Request $request, Response $response, array $args) {
  if(!checkAllowedReferer ()) {
    $response = responseError($response, 'Not allowed referer', $args, 400);
    return $response;    
  }

  $sites = getAllWhiteListedSites ();
  $response->getBody()->write(json_encode($sites));
	return $response;
});

$app->run();

//FUNCTIONS ---------------------------
function getActiveCammpignEmailByContactId ($contact_id) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contacts/".$contact_id);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
	$result = json_decode(curl_exec($curl));
	return $result->contact->email;
}

function getAllAllowedEvents () {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/eventTrackingEvents");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
	$result = json_decode(curl_exec($curl));
    $events = array();
    foreach ($result->eventTrackingEvents as $event)  {
        $events[] = $event->name;
    }
	return $events;
}

function getAllWhiteListedSites () {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/siteTrackingDomains");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
	$result = json_decode(curl_exec($curl));
    $sites = array();
    foreach ($result->siteTrackingDomains as $site)  {
        $sites[] = $site->name;
    }
    $sites[] = 'apidomain.com';
	return $sites;
}  

function responseError($response, $message, $args, $error_code = 400) {
  $body['status'] = 'Error';
  $body['message'] = $message;
  $body['args'] = $args;
  $response->getBody()->write(json_encode($body));
  $response = $response->withStatus($error_code);
  return $response;
}

function checkAllowedReferer () {
  $refData = parse_url($_SERVER['HTTP_REFERER']);
  $allowed_referers = getAllWhiteListedSites();
  if (in_array($refData['host'], $allowed_referers)) return true;
  else return false;
}
