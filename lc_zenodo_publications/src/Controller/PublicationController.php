<?php

namespace Drupal\lc_zenodo_publications\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PublicationController extends ControllerBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
    );
  }

  public function listPublications(): array {
    $config = $this->configFactory->get('lc_zenodo_publications.settings');
    $size = $config->get('size');
    $community = $config->get('community');
    $path = "https://zenodo.org/api/records?page=1&size=$size&communities=$community&sort=mostrecent";
    $path_page = "https://zenodo.org/communities/$community/records?q=&l=list&p=1&s=10&sort=newest";

    $response = $this->httpClient->get($path);
    $data = json_decode($response->getBody()->getContents(), TRUE);


    $n_pubs = count($data['hits']['hits']);
    $publications = [];
    foreach ($data['hits']['hits'] as $publication) {

      $authors = [];
      $creators = $publication['metadata']['creators'];

      foreach($creators as $creator) {

          if(isset($creator['orcid'])) {
              $authors[] = "<a class=\"pub-author\" title=\"Affiliation: {$creator['affiliation']}\" "
                  . "href=\"https://https://orcid.org/{$creator['orcid']}\">"
                  . $creator['name'] . "</a>";
          }
          else {
              $authors[] = "<span class=\"pub-author\" title=\"Affiliation: {$creator['affiliation']}\">"
                  . $creator['name'] . "</span>";
          }
      }

      $publications[] = [
        'title' => $publication['metadata']['title'],
        'description' => $publication['metadata']['description'] ?? null,
        'doi' => $publication['metadata']['doi'],
        'publication_date' => (new \Datetime($publication['metadata']['publication_date']))->format('d/m/Y'),
        'authors' => $authors,
      ];
    }

    // print_r($publications); exit;

    $renderable = [
      '#theme' => 'publications_list',
      '#publications' => $publications,
      '#n_pubs' => $n_pubs,
      '#path_page' => $path_page,
    ];

    return $renderable;

  }

}
