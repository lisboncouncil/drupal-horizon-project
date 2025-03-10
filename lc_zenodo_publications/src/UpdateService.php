<?php

namespace Drupal\lc_zenodo_publications;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\CronInterface;

class UpdateService {

  protected $configFactory;
  protected $httpClient;

  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  public function updatePublications() {
    $config = $this->configFactory->get('lc_zenodo_publications.settings');
    $size = $config->get('size');
    $community = $config->get('community');
    $path = "https://zenodo.org/api/records?page=1&size=$size&communities=$community&sort=mostrecent";

    $response = $this->httpClient->get($path);
    $data = json_decode($response->getBody()->getContents(), TRUE);
    
    // Save or update the data in the database or cache.
    // This part depends on your specific needs for storing the data.
  }

}
