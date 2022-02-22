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
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $events = CallApiActiveCampaign::getAllAllowedEvents ();
      $response->getBody()->write(json_encode($events));
    return $response;
  });

  $app->put('/event/{event_name:[a-z0-9\-]+}/{contact_id:[0-9]+}', function (Request $request, Response $response, array $args) use ($app) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
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
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
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
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $sites = CallApiActiveCampaign::getAllWhiteListedSites ();
    $response->getBody()->write(json_encode($sites));
    return $response;
  });
  
  $app->put('/automation/{contact_id:[0-9]+}/{automation_id:[0-9\,]+}', function (Request $request, Response $response, array $args) use ($app) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

		foreach (explode(",", $args['automation_id']) as $automation_id) {
			$data['contactAutomation'] = [
		    "contact" => $args['contact_id'],
		    "automation" => $automation_id
		  ];
		  
		  $payload = json_encode($data);
		  $curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contactAutomations");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		  $result = curl_exec($curl);
		  if ($result !== false) {
		    $result = json_decode($result);
		    if ($result->contacts[0]->id == $args['contact_id']) {
		      $body['status'] = 'OK';
		    } else {
		      $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
          return $response;
		    }
		  } else {
		    $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
        return $response;
		  }

		  $body['args'] = $args;
		}
		$response->getBody()->write(json_encode($body));
    return $response;
  });
    
  $app->put('/tag/{contact_id:[0-9]+}/{tag_id:[0-9\,]+}', function (Request $request, Response $response, array $args) use ($app) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

		foreach (explode(",", $args['tag_id']) as $tag_id) {
      $data['contactTag'] = [
        "contact" => $args['contact_id'],
        "tag"     => $tag_id
      ];
		  
		  $payload = json_encode($data);
		  $curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contactTags");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		  $result = curl_exec($curl);
		  if ($result !== false) {
		    $result = json_decode($result);
		    if ($result->contacts[0]->id == $args['contact_id'] || $result->contactTag->id > 0) {
		      $body['status'] = 'OK';
		    } else {
		      $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
          return $response;
		    }
		  } else {
		    $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
        return $response;
		  }

		  $body['args'] = $args;
 	  }
		$response->getBody()->write(json_encode($body));
    return $response;
  });
  
  $app->delete('/tag/{contact_id:[0-9]+}/{tag_id:[0-9\,]+}', function (Request $request, Response $response, array $args) use ($app) {
    if(!CallApiActiveCampaign::checkAllowedReferer ()) {
      $refData = parse_url($_SERVER['HTTP_REFERER']);
      $args['host'] = $refData['host'];
      $response = CallApiActiveCampaign::responseError($response, 'Not allowed referer', $args, 400);
      return $response;    
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contacts/".$args['contact_id']."/contactTags");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
    $responseTags = curl_exec($curl);
    $userTags = json_decode($responseTags)->contactTags;
    curl_close($curl);
    foreach($userTags as $userTag) {
      $tags[$userTag->tag] = $userTag->id;
    }

		foreach (explode(",", $args['tag_id']) as $tag_id) {
      if(isset($tags[$tag_id])) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contactTags/".$tags[$tag_id]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        $result = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (in_array($httpcode, array(200, 201))) {
          $body['status'] = 'OK';
        } else {
          $response = CallApiActiveCampaign::responseError($response, 'ActiveCAmpaign API not avaliable', $args, 400);
          return $response;
        }
      }
      $body['args'] = $args;
		}
		$response->getBody()->write(json_encode($body));
    return $response;
  });
  //curl --referer https://spri.eus -X PUT https://apispri.enuttisworking.com/tag/61496/173,174
  //curl --referer https://spri.eus -X DELETE https://apispri.enuttisworking.com/tag/61496/173,174
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
};
