<?php

use CallApiActiveCampaign\ApiActiveCampaign as CallApiActiveCampaign;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

   
  $app->options('/{routes:.+}', function ($request, $response, $args) { //CORS -> aceptamos todas las peticiones OPTIONS para superar el CORS
    return $response;
    /*return $response->withHeader('Access-Control-Allow-Origin', '*')  
    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');*/
  });

  $app->get('/event/all', function (Request $request, Response $response, array $args) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $events = CallApiActiveCampaign::getAllAllowedEvents ();
      $response->getBody()->write(json_encode($events));
    return $response;
  });

  $app->put('/event/{event_name:[a-z0-9\-]+}/{contact_id:[0-9]+}', function (Request $request, Response $response, array $args) use ($app) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $allowed_events = CallApiActiveCampaign::getAllAllowedEvents ();

    if(!in_array($args['event_name'], $allowed_events)) { //NO es uno de los eventos registrados
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed {event name}', $args, 400);
      return $response;
    }

    $email = CallApiActiveCampaign::getActiveCammpignEmailByContactId ($args['contact_id']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {  //NO existe el usuario
      $response = CallApiActiveCampaign::responseError($response, '{contact_id} not exist', $args, 400);
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
        $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
      }
    } else {
      $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
    }

    $body['args'] = $args;
    $response->getBody()->write(json_encode($body));

    return $response;
  });

  $app->get('/contact/email/{contact_id:[0-9]+}', function (Request $request, Response $response, array $args) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $email = CallApiActiveCampaign::getActiveCammpignEmailByContactId ($args['contact_id']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {  //NO existe el usuario
      $response = CallApiActiveCampaign::responseError($response, '{contact_id} not exist', $args, 400);
      return $response;
    } else $response->getBody()->write(json_encode(array("email" => $email)));
    return $response;
  });

  $app->get('/site/all', function (Request $request, Response $response, array $args) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $sites = CallApiActiveCampaign::getAllWhiteListedSites ();
    $response->getBody()->write(json_encode($sites));
    return $response;
  });
};
