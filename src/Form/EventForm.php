<?php

namespace Drupal\custom_events\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_events\Service\EventService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventForm extends FormBase {

  protected EventService $eventService;

  public function __construct(EventService $eventService) {
    $this->eventService = $eventService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('custom_events.event_service')
    );
  }

  public function getFormId(): string {
    return 'custom_events_event_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['title'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Título del evento'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => $this->t('Ej: Congreso de Tecnología 2025'),
    ];

    $form['description'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Descripción'),
      '#required'    => TRUE,
      '#rows'        => 5,
      '#placeholder' => $this->t('Describe el evento...'),
    ];

    $form['country_code'] = [
      '#type'        => 'select',
      '#title'       => $this->t('País'),
      '#options'     => $this->eventService->getCountriesForSelect(),
      '#required'    => TRUE,
      '#description' => $this->t('Países obtenidos desde restcountries.com'),
    ];

    $form['event_date'] = [
      '#type'       => 'date',
      '#title'      => $this->t('Fecha del evento'),
      '#required'   => TRUE,
      '#attributes' => ['min' => date('Y-m-d', strtotime('+1 day'))],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Crear Evento'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'custom_events/custom_events_assets';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $date = $form_state->getValue('event_date');
    if (!empty($date) && strtotime($date) <= strtotime('today')) {
      $form_state->setErrorByName('event_date', $this->t('La fecha debe ser futura.'));
    }
    if (empty($form_state->getValue('country_code'))) {
      $form_state->setErrorByName('country_code', $this->t('Debes seleccionar un país.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $eventId = $this->eventService->createEvent([
      'title'        => $form_state->getValue('title'),
      'description'  => $form_state->getValue('description'),
      'country_code' => $form_state->getValue('country_code'),
      'event_date'   => $form_state->getValue('event_date'),
    ]);

    if ($eventId) {
      $this->messenger()->addStatus($this->t('¡Evento creado con ID @id!', ['@id' => $eventId]));
      $form_state->setRedirect('custom_events.list');
    } else {
      $this->messenger()->addError($this->t('Error al crear el evento.'));
    }
  }
}
