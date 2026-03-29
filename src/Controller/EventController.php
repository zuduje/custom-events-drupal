<?php

namespace Drupal\custom_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_events\Service\EventService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventController extends ControllerBase {

  protected EventService $eventService;

  public function __construct(EventService $eventService) {
    $this->eventService = $eventService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('custom_events.event_service')
    );
  }

  public function listEvents(): array {
    $events      = $this->eventService->getActiveEvents();
    $currentUser = $this->currentUser();
    $isLoggedIn  = !$currentUser->isAnonymous();

    $enriched = [];
    foreach ($events as $event) {
      $isRegistered = $isLoggedIn
        ? $this->eventService->isUserRegistered((int) $event->event_id, (int) $currentUser->id())
        : FALSE;

      $enriched[] = [
        'event_id'        => $event->event_id,
        'title'           => $event->title,
        'description'     => $event->description,
        'country'         => $event->country,
        'country_code'    => $event->country_code,
        'event_date'      => $event->event_date,
        'event_date_fmt'  => date('d/m/Y', strtotime($event->event_date)),
        'inscribed_count' => $event->inscribed_count,
        'is_registered'   => $isRegistered,
        'is_past'         => strtotime($event->event_date) < strtotime('today'),
      ];
    }

    return [
      '#theme'        => 'custom_events_list',
      '#events'       => $enriched,
      '#is_logged_in' => $isLoggedIn,
      '#is_admin'     => $currentUser->hasPermission('administer site configuration'),
      '#user_name'    => $isLoggedIn ? $currentUser->getDisplayName() : NULL,
      '#attached'     => ['library' => ['custom_events/custom_events_assets']],
      '#cache'        => ['max-age' => 0],
    ];
  }

  public function eventDetail(int $event_id): array {
    $event = $this->eventService->getEvent($event_id);

    if (!$event) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $currentUser  = $this->currentUser();
    $isLoggedIn   = !$currentUser->isAnonymous();
    $isRegistered = $isLoggedIn
      ? $this->eventService->isUserRegistered($event_id, (int) $currentUser->id())
      : FALSE;

    return [
      '#theme'        => 'custom_events_detail',
      '#event'        => [
        'event_id'        => $event->event_id,
        'title'           => $event->title,
        'description'     => $event->description,
        'country'         => $event->country,
        'country_code'    => $event->country_code,
        'event_date'      => $event->event_date,
        'event_date_fmt'  => date('d/m/Y', strtotime($event->event_date)),
        'inscribed_count' => $this->eventService->getRegistrationCount($event_id),
        'is_registered'   => $isRegistered,
        'is_past'         => strtotime($event->event_date) < strtotime('today'),
      ],
      '#is_logged_in' => $isLoggedIn,
      '#attached'     => ['library' => ['custom_events/custom_events_assets']],
      '#cache'        => ['max-age' => 0],
    ];
  }
}
