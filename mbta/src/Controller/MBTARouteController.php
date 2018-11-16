<?php

namespace Drupal\mbta\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mbta\MBTARouteDownloader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the MBTA data.
 */
class MBTARouteController extends ControllerBase {

  /**
   * @var \Drupal\mbta\MBTARouteDownloader
   */
  protected $routeDownloader;

  /**
   * MBTARouteController constructor.
   *
   * @param \Drupal\mbta\MBTARouteDownloader $routeDownloader
   */
  public function __construct(MBTARouteDownloader $routeDownloader) {
    $this->routeDownloader = $routeDownloader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mbta.route_downloader')
    );
  }

  /**
   *
   * this method gets all the MBTA routes as a table
   *
   * @return array
   */
  public function getAllMBTARoutes() {
    return $this->routeDownloader->getRoutes();
  }


  /**
   *
   * this method gets all the stops for an MBTA route
   *
   * @param $name
   *
   * @return array|null
   */
  public function getMBTARoute($name) {
    return $this->routeDownloader->getRoute($name);
  }

  /**
   * this method gets all the schedule times for a stop on a specific route.
   * not all stops have schedule times
   *
   * @param $route
   * @param $stop
   *
   * @return array
   */
  public function getMBTASchedule($route, $stop) {
    return $this->routeDownloader->getSchedule($route, $stop);
  }

}