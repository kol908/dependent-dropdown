<?php

namespace Drupal\dependent_dropdown\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;

/**
 * This form is shown in modal for adding new field.
 */
class AddFields extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dependent_dropdown_add_fields';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['dependent_dropdown.select'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $contentTypeId = NULL) {
    $form['#prefix'] = '<div id="dependent_dropdown_add_fields">';
    $form['#suffix'] = '</div>';

    $form['add_number'] = [
      '#type' => 'link',
      '#title' => 'Add Number Field',
      '#url' => Url::fromRoute(
        'dependent_dropdown.new_field_settings',
        ['contentType' => $contentTypeId, 'fieldType' => 'number']
      ),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
        ],
      ],
    ];

    $form['add_select_list'] = [
      '#type' => 'link',
      '#title' => 'Add Select List Field',
      '#url' => Url::fromRoute(
        'dependent_dropdown.new_field_settings',
        ['contentType' => $contentTypeId, 'fieldType' => 'select']
      ),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
        ],
      ],
    ];

    $form['add_ref_select_list'] = [
      '#type' => 'link',
      '#title' => 'Add Reference Select List Field',
      '#url' => Url::fromRoute(
        'dependent_dropdown.new_field_settings',
        ['contentType' => $contentTypeId, 'fieldType' => 'ref_select']
      ),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#dependent_dropdown_add_fields', $form));
    }
    else {
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 800]));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
