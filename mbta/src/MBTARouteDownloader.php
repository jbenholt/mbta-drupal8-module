<?php

namespace Drupal\mbta;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Link;


class MBTARouteDownloader {

  use StringTranslationTrait;

  /**
   * constants should be turned into configuration options with special permissions to edit
   */

  /**
   * base url to get all the routes from the MBTA
   */
  const BASE_MBTA_URL = 'https://api-v3.mbta.com/routes';

  /**
   * base url used to get all the stop on a route
   */
  const BASE_MBTA_STOPS_URL = 'https://api-v3.mbta.com/stops?include=route&filter[route]=';

  /**
   * base url used to get scheduling information for a route
   */
  const BASE_MBTA_SCHEDULE_URL = 'https://api-v3.mbta.com/schedules';

  /**
   * default error message shown to user
   */
  const ERROR_MESSAGE = 'Something went wrong with your request';


  /**
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * MBTARouteDownloader constructor.
   *
   * @param \GuzzleHttp\Client $httpClient
   */
  public function __construct(Client $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   *
   * function that gets all routes from the MBTA and returns a renderable array
   *
   * @return array
   */
  public function getRoutes() {

    $render = [];


    $url = self::BASE_MBTA_URL;

    // Get the JSON data from the MBTA API
    $responseBody = $this->getDataFromURL($url);

    // If no data set error message to end user
    if (!$responseBody) {
      $this->setErrorMessage($render);
      return $render;
    }

    //process the data for rendering
    $dataDecoded = $this->processJSONDataAllRoutes($responseBody);

    // If no data set give error message to end user
    if (!$dataDecoded) {
      $this->setErrorMessage($render);
      return $render;
    }

    $render = $dataDecoded;

    return $render;

  }

  /**
   *
   * Gets a list of stop availble on a route and returns a render array
   *
   * @param null $route
   *
   * @return array|null
   */
  public function getRoute($route = NULL) {

    $render = [];

    if(!$route) {
      $this->setErrorMessage($render, 'Please provide a valid MBTA Route');
      return $render;
    }

    $url = self::BASE_MBTA_STOPS_URL . $route;

    $responseBody = $this->getDataFromURL($url);

    // If no data set error message to end user
    if (!$responseBody) {
      $this->setErrorMessage($render);
      return $render;
    }

    //process the data for rendering
    $dataDecoded = $this->processJSONDataRoute($responseBody, $route);

    // If no data set give error message to end user
    if (!$dataDecoded) {
      $this->setErrorMessage($render);
      return $render;
    }

    $render = $dataDecoded;

    return $render;

  }

  /**
   *
   * get a list of schedule times for a route and stop with times no older than
   * 30 minutes ago. Returns a render array
   *
   * @param null $route
   * @param null $stop
   *
   * @return array
   */
  public function getSchedule($route = NULL, $stop = NULL) {

    $render = [];

    if(!$route || !$stop) {
      $this->setErrorMessage($render);
      return $render;
    }

    //Set the minimum time to 30 minutes ago so as to not get too many results
    $time30Ago = date('H:i',mktime(date("H"), date("i")-30, date("s"), date("n"), date('j'),date('Y')));

    $url = self::BASE_MBTA_SCHEDULE_URL . "?include=route,direction_id&filter[route]={$route}&filter[stop]={$stop}&filter[min_time]={$time30Ago}&filter[direction_id]=";
    $urlDirection0 = $url . '0';
    $urlDirection1 = $url . '1';

    //First Direction
    $responseBody = $this->getDataFromURL($urlDirection0);

    // If no data set error message to end user
    if (!$responseBody) {
      $this->setErrorMessage($render, 'Unable to find any Schedule Times');
      return $render;
    }

    //process the data for rendering
    $dataDecoded = $this->processJSONDataSchedule($responseBody, $route, $stop, 0);

    // If no data set give error message to end user
    if (!$dataDecoded) {
      $this->setErrorMessage($render, 'Unable to find any Schedule Times');
      return $render;
    }

    $render['table1'] = $dataDecoded;

    //Second Direction
    $responseBody = $this->getDataFromURL($urlDirection1);

    // If no data set error message to end user
    if (!$responseBody) {
      $this->setErrorMessage($render, 'Unable to find any Schedule Times');
      return $render;
    }

    //process the data for rendering
    $dataDecoded = $this->processJSONDataSchedule($responseBody, $route, $stop, 1);

    // If no data set give error message to end user
    if (!$dataDecoded) {
      $this->setErrorMessage($render, 'Unable to find any Schedule Times');
      return $render;
    }

    $render['table2'] = $dataDecoded;

    return $render;

  }

  /**
   *
   * Gets a response from an extenal url. The expected data is JSON
   *
   * @param $url
   *
   * @return null|\Psr\Http\Message\StreamInterface
   */
  private function getDataFromURL($url) {
    $JSON = NULL;
    try {
      $request = $this->httpClient->get($url);
      $JSON = $request->getBody();
    } catch(\Exception $e) {
      //Log error message here to watchdog
      return NULL;
    }
    return $JSON;
  }

  /**
   *
   * takes a request body and decodes it from JSON. Only valid JSON is accepted.
   * Returns a render array of a table made up all MBTA routes
   *
   * @param $JSON
   *
   * @return array|null
   */
  private function processJSONDataAllRoutes($JSON) {

    $render = [];


    $headers = [
      $this->t('Route ID'),
      $this->t('Full Name'),
      $this->t('Directions')
      ];

    $JSON = JSON::decode($JSON);

    if(!$JSON) {
      //Log error here
      return NULL;
    }
    if (!isset($JSON['data']) || !count($JSON['data'])) {
      //log error message
      return NULL;
    }

//    return '<pre>'.print_r($JSON,true).'</pre>';

    //Go through data and build rows of the table
    foreach ($JSON['data'] as $key => $routeItem) {

      $routeID = $routeItem['id'];

      $routeAttributes = $routeItem['attributes'];
      $routeDescription = $routeAttributes['description'];
      $routeDirections = implode(', ',$routeAttributes['direction_names']);
      $routeName = $routeAttributes['long_name'];
      $routeTextColor = $routeAttributes['text_color'];
      $routeColor = $routeAttributes['color'];


      $style = "color: #{$routeTextColor}; background-color: #{$routeColor};";

      $url = Url::fromRoute('mbta.route', array('name' => $routeID));
      $link = Link::fromTextAndUrl($routeID, $url);
      $linkRender = $link->toRenderable();
      $linkRender['#attributes'] = array('style' => $style);

      $routeItems = [
        ['data' => [$linkRender], 'style' => $style],
        ['data' => ['#markup' => $routeName], 'style' => $style],
        ['data' => ['#markup' => $routeDirections], 'style' => $style],
      ];


      if (isset($render[$routeDescription])) {
        $render[$routeDescription]['#rows'][] = $routeItems;
      } else {
        $render[$routeDescription] = [
          '#type' => 'table',
          '#caption' => $routeDescription,
          '#header' => $headers,
          '#rows' => [$routeItems]
        ];
      }

    }

    return $render;

  }

  /**
   *
   * takes a request body and decodes it from JSON. Only valid JSON is accepted.
   * A render array is returned that has all stops for a specific route
   *
   *
   * @param $JSON
   * @param $route
   *
   * @return array|null
   */
  private function processJSONDataRoute($JSON, $route) {


    $JSON = JSON::decode($JSON);

    if(!$JSON) {
      //Log error here
      return NULL;
    }
    if (!isset($JSON['data']) || !count($JSON['data'])) {
      //log error message
      return NULL;
    }

    $stopItems = [];

    $headers = [
      $this->t('Stop ID'),
      $this->t('Name'),
      $this->t('Stop Address')
    ];

    $long_name = $JSON['included'][0]['attributes']['long_name'];

    foreach ($JSON['data'] as $stopItem) {


      $stopId = $stopItem['id'];
      $stopName = $stopItem['attributes']['name'];
      $stopAddress = $stopItem['attributes']['address'];

      $url = Url::fromRoute('mbta.schedule', array('route' => $route, 'stop' => $stopId));
      $link = Link::fromTextAndUrl($stopId, $url);
      $linkRender = $link->toRenderable();

      $stopItems[] = [
        ['data' => [$linkRender]],
        ['data' => ['#markup' => $stopName]],
        ['data' => ['#markup' => $stopAddress]]
      ];

    }

    $render = [
      '#type' => 'table',
      '#caption' => 'Route ID: '.$route . ', Long Name: ' . $long_name,
      '#header' => $headers,
      '#rows' => $stopItems
    ];

    return $render;

  }

  /**
   *
   * takes a request body and decodes it from JSON. Only valid JSON is accepted.
   * returns a render array of schedule times, usually two tables are output for
   * the two directions the trains go. Sometimes special events and less used stops
   * do not have any schedule times to show.
   *
   * @param $JSON
   * @param $route
   * @param $stop
   *
   * direction the train is going
   * @param $direction
   *
   * @return array|null
   */
  private function processJSONDataSchedule($JSON, $route, $stop, $direction) {

    $JSON = JSON::decode($JSON);

    if(!$JSON) {
      //Log error here
      return NULL;
    }
    if (!isset($JSON['data']) || !count($JSON['data'])) {
      //log error message
      return NULL;
    }

    $scheduleItems = [];

    $headers = [
      $this->t('Arrival Time'),
      $this->t('Departure Time'),
      $this->t('Schedule ID')
    ];
    $long_name = $JSON['included'][0]['attributes']['long_name'];
    $direction_name = $JSON['included'][0]['attributes']['direction_names'][$direction];

    foreach ($JSON['data'] as $scheduleItem) {

      $scheduleID = $scheduleItem['id'];
      $arrival = $scheduleItem['attributes']['arrival_time'];
      $departure = $scheduleItem['attributes']['departure_time'];

      $scheduleItems[] = [$arrival,$departure,$scheduleID];
    }

    $render = [
      '#type' => 'table',
      '#caption' => "Route ID: {$route} , Long Name: {$long_name} at Stop ID: {$stop} Going Direction: {$direction_name}",
      '#header' => $headers,
      '#rows' => $scheduleItems
    ];

    return $render;

  }

  /**
   *
   * helper function to format a render array with an error message shown to the user.
   *
   * @param $render
   * @param null $message
   */
  private function setErrorMessage(&$render, $message = NULL) {
    if($message) {
      $render['error']['#markup'] = $message;
    }
    else {
      $render['#markup'] = self::ERROR_MESSAGE;
    }
  }
}