<?php

namespace Drupal\dependent_dropdown\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;

/**
 * All the modals are built in this class.
 */
class ModalFormController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The ModalFormController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Callback for opening the modal form to edit a field settings.
   */
  public function editSettings(string $contentType = NULL, string $fieldName = NULL) {
    $response = new AjaxResponse();
    $removeField = $fieldName;
    $contentTypeId = $contentType;

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\dependent_dropdown\Form\EditSettings', $contentTypeId, $removeField);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Edit Settings', $modal_form, ['width' => '800']));

    return $response;
  }

  /**
   * Callback for opening the modal form for adding new fields.
   */
  public function addFields(string $contentType = NULL) {
    $response = new AjaxResponse();

    $modal_form = $this->formBuilder->getForm('Drupal\dependent_dropdown\Form\AddFields', $contentType);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Add Fields', $modal_form, ['width' => '800']));

    return $response;
  }

  /**
   * Callback for opening the modal form for configuring new field settings.
   */
  public function newFieldSettings(string $contentType = NULL, string $fieldType = NULL) {
    $response = new AjaxResponse();

    $modal_form = $this->formBuilder->getForm('Drupal\dependent_dropdown\Form\NewFieldSettings', $contentType, $fieldType);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Field Settings', $modal_form, ['width' => '800']));

    return $response;
  }

}
