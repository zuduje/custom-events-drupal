<?php

namespace Drupal\custom_events\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class EventService {

  const COUNTRIES_API_URL = 'https://restcountries.com/v3.1/all?fields=name,cca2,flags';
  const CACHE_KEY = 'custom_events:countries';
  const CACHE_TTL = 86400;

  protected Connection $database;
  protected AccountInterface $currentUser;
  protected CacheBackendInterface $cache;
  protected ClientInterface $httpClient;
  protected $logger;

  public function __construct(
    Connection $database,
    AccountInterface $currentUser,
    CacheBackendInterface $cache,
    ClientInterface $httpClient,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->database    = $database;
    $this->currentUser = $currentUser;
    $this->cache       = $cache;
    $this->httpClient  = $httpClient;
    $this->logger      = $loggerFactory->get('custom_events');
  }

  public function getCountries(): array {
    $cached = $this->cache->get(self::CACHE_KEY);
    if ($cached) {
      return $cached->data;
    }
    try {
      $response = $this->httpClient->request('GET', self::COUNTRIES_API_URL, [
        'timeout' => 10,
      ]);
      $data = json_decode($response->getBody()->getContents(), TRUE);
      if (empty($data)) {
        return $this->getFallbackCountries();
      }
      $countries = array_map(function ($c) {
        return [
          'code' => $c['cca2'] ?? '',
          'name' => $c['name']['common'] ?? '',
          'flag' => $c['flags']['emoji'] ?? '',
        ];
      }, $data);
      usort($countries, fn($a, $b) => strcmp($a['name'], $b['name']));
      $this->cache->set(self::CACHE_KEY, $countries, \Drupal::time()->getRequestTime() + self::CACHE_TTL);
      return $countries;
    } catch (RequestException $e) {
      $this->logger->error('Error restcountries.com: @msg', ['@msg' => $e->getMessage()]);
      return $this->getFallbackCountries();
    }
  }

  protected function getFallbackCountries(): array {
    return [
      ['code' => 'CO', 'name' => 'Colombia',      'flag' => '🇨🇴'],
      ['code' => 'MX', 'name' => 'México',         'flag' => '🇲🇽'],
      ['code' => 'AR', 'name' => 'Argentina',      'flag' => '🇦🇷'],
      ['code' => 'ES', 'name' => 'España',         'flag' => '🇪🇸'],
      ['code' => 'US', 'name' => 'Estados Unidos', 'flag' => '🇺🇸'],
      ['code' => 'BR', 'name' => 'Brasil',         'flag' => '🇧🇷'],
      ['code' => 'CL', 'name' => 'Chile',          'flag' => '🇨🇱'],
      ['code' => 'PE', 'name' => 'Perú',           'flag' => '🇵🇪'],
    ];
  }

  public function getCountriesForSelect(): array {
    $countries = $this->getCountries();
    $options = ['' => '-- Seleccione un país --'];
    foreach ($countries as $c) {
      if (!empty($c['code']) && !empty($c['name'])) {
        $options[$c['code']] = $c['flag'] . ' ' . $c['name'];
      }
    }
    return $options;
  }

  public function getCountryNameByCode(string $code): string {
    foreach ($this->getCountries() as $c) {
      if ($c['code'] === $code) return $c['name'];
    }
    return $code;
  }

  public function createEvent(array $data): int {
    return $this->database->insert('custom_events')
      ->fields([
        'title'        => $data['title'],
        'description'  => $data['description'],
        'country'      => $this->getCountryNameByCode($data['country_code']),
        'country_code' => $data['country_code'],
        'event_date'   => $data['event_date'],
        'created'      => \Drupal::time()->getRequestTime(),
        'uid'          => $this->currentUser->id(),
        'status'       => 1,
      ])
      ->execute();
  }

  public function getActiveEvents(): array {
    $query = $this->database->select('custom_events', 'e')
      ->fields('e', ['event_id', 'title', 'description', 'country', 'country_code', 'event_date'])
      ->condition('e.status', 1)
      ->orderBy('e.event_date', 'ASC');

    $countQuery = $this->database->select('custom_event_registrations', 'r')
      ->fields('r', ['event_id']);
    $countQuery->addExpression('COUNT(*)', 'total');
    $countQuery->groupBy('r.event_id');

    $query->leftJoin($countQuery, 'cnt', 'cnt.event_id = e.event_id');
    $query->addExpression('COALESCE(cnt.total, 0)', 'inscribed_count');

    return $query->execute()->fetchAll();
  }

  public function getEvent(int $eventId): ?object {
    return $this->database->select('custom_events', 'e')
      ->fields('e')
      ->condition('e.event_id', $eventId)
      ->condition('e.status', 1)
      ->execute()
      ->fetchObject() ?: NULL;
  }

  public function isUserRegistered(int $eventId, int $userId): bool {
    $count = $this->database->select('custom_event_registrations', 'r')
      ->condition('r.event_id', $eventId)
      ->condition('r.uid', $userId)
      ->countQuery()
      ->execute()
      ->fetchField();
    return (int) $count > 0;
  }

  public function getRegistrationCount(int $eventId): int {
    return (int) $this->database->select('custom_event_registrations', 'r')
      ->condition('r.event_id', $eventId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function registerUser(int $eventId, int $userId): array {
    $event = $this->getEvent($eventId);
    if (!$event) {
      return ['success' => FALSE, 'message' => 'El evento no existe.'];
    }
    if ($this->isUserRegistered($eventId, $userId)) {
      return ['success' => FALSE, 'message' => 'Ya estás inscrito en este evento.'];
    }
    if (strtotime($event->event_date) < strtotime('today')) {
      return ['success' => FALSE, 'message' => 'Este evento ya ocurrió.'];
    }
    try {
      $this->database->insert('custom_event_registrations')
        ->fields([
          'event_id'   => $eventId,
          'uid'        => $userId,
          'registered' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();
      return ['success' => TRUE, 'message' => '¡Inscripción exitosa!', 'event' => $event];
    } catch (\Exception $e) {
      if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
        return ['success' => FALSE, 'message' => 'Ya estás inscrito en este evento.'];
      }
      $this->logger->error('Error inscripción: @msg', ['@msg' => $e->getMessage()]);
      return ['success' => FALSE, 'message' => 'Error al procesar la inscripción.'];
    }
  }
}
