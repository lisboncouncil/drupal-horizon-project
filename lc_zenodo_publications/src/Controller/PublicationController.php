<?php

namespace Drupal\lc_zenodo_publications\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PublicationController extends ControllerBase {

  public function __construct(
    protected ConfigFactoryInterface $config_factory,
    protected ClientInterface $http_client,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
    );
  }

  public function listPublications(): array {
    $config = $this->config_factory->get('lc_zenodo_publications.settings');
    $size = $config->get('size');
    $community = $config->get('community');
    $path = "https://zenodo.org/api/records?page=1&size=$size&communities=$community&sort=mostrecent";
    $path_page = "https://zenodo.org/communities/$community/records?q=&l=list&p=1&s=10&sort=newest";

    $response = $this->http_client->get($path);
    $data = json_decode($response->getBody()->getContents(), TRUE);


    $n_pubs = count($data['hits']['hits']);
    $publications = [];
    foreach ($data['hits']['hits'] as $publication) {

      $authors = [];
      $creators = $publication['metadata']['creators'];

      foreach($creators as $creator) {

          if(isset($creator['orcid'])) {
              $authors[] = "<a class=\"pub-author\" title=\"Affiliation: {$creator['affiliation']}\" "
                  . "href=\"https://orcid.org/{$creator['orcid']}\">"
                  . $creator['name'] . "</a>";
          }
          else {
              $authors[] = "<span class=\"pub-author\" title=\"Affiliation: {$creator['affiliation']}\">"
                  . $creator['name'] . "</span>";
          }
      }

      $files = [];
      foreach ($publication['files'] ?? [] as $file) {
        $key = (string) $file['key'];
        $files[] = [
          'name' => basename($key),
          'size' => (int) ($file['size'] ?? 0),
          'url' => "https://zenodo.org/records/{$publication['id']}/files/"
            . implode('/', array_map('rawurlencode', explode('/', $key))) . '?download=1',
        ];
      }

      $publications[] = [
        'title' => $publication['metadata']['title'],
        'description' => $publication['metadata']['description'] ?? null,
        'doi' => $publication['metadata']['doi'],
        'publication_date' => (new \Datetime($publication['metadata']['publication_date']))->format('d/m/Y'),
        'authors' => $authors,
        // ISO date (Y-m-d), for themes that format it themselves.
        'date' => $publication['metadata']['publication_date'],
        'keywords' => $publication['metadata']['keywords'] ?? [],
        // Zenodo type and subtype ids ("publication", "report") and its
        // English label ("Report").
        'resource_type' => [
          'type' => $publication['metadata']['resource_type']['type'] ?? '',
          'subtype' => $publication['metadata']['resource_type']['subtype'] ?? '',
          'title' => $publication['metadata']['resource_type']['title'] ?? '',
        ],
        'files' => $files,
        'record_url' => $publication['links']['self_html'] ?? '',
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
