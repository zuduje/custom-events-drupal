<?php

namespace Drupal\custom_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_events\Service\EventService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EventApiController extends ControllerBase {

  protected EventService $eventService;

  public function __construct(EventService $eventService) {
    $this->eventService = $eventService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('custom_events.event_service')
    );
  }

  public function registerUser(Request $request, int $event_id): JsonResponse {
    $currentUser = $this->currentUser();

    if ($currentUser->isAnonymous()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Debes iniciar sesión.',
      ], 403);
    }

    $result = $this->eventService->registerUser($event_id, (int) $currentUser->id());

    return new JsonResponse([
      'success'   => $result['success'],
      'message'   => $result['message'],
      'event_id'  => $event_id,
      'new_count' => $this->eventService->getRegistrationCount($event_id),
    ], $result['success'] ? 200 : 400);
  }
}
