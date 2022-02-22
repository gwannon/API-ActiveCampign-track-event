<?php

namespace CallApiActiveCampaign;

class ApiActiveCampaign {
  public function getActiveCammpignEmailByContactId ($contact_id) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/contacts/".$contact_id);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
    $result = json_decode(curl_exec($curl));
    return $result->contact->email;
  }

  public function getAllAllowedEvents () {
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

  public function getAllWhiteListedSites () {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, AC_API_DOMAIN."/api/3/siteTrackingDomains");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Api-Token: '.AC_API_TOKEN));
    $result = json_decode(curl_exec($curl));
    $sites = array();
    foreach ($result->siteTrackingDomains as $site)  {
        $sites[] = $site->name;
    }
    $sites[] = 'enutt.net';
    $sites[] = 'apispri.enuttisworking.com';
    return $sites;
  }  

  public function checkAllowedReferer () {
    $refData = parse_url($_SERVER['HTTP_REFERER']);
    $allowed_referers = self::getAllWhiteListedSites();
    if (in_array(str_replace("www.", "", $refData['host']), $allowed_referers)) return true;
    else return false;
  }

  public function responseError($response, $message, $args, $error_code = 400) {
    $body['status'] = 'Error';
    $body['message'] = $message;
    $body['args'] = $args;
    $response->getBody()->write(json_encode($body));
    $response = $response->withStatus($error_code);
    return $response;
  }
}
