<?php

namespace Drupal\dependent_dropdown\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;

/**
 * This form is shown in modal for configuring new field settings.
 */
class NewFieldSettings extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a NewFieldSettings object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactory $config_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dependent_dropdown_new_field_settings';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['dependent_dropdown.elements'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $contentTypeId = NULL, string $fieldType = NULL) {
    $form['#prefix'] = '<div id="dependent_dropdown_new_field_settings">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['field_label'] = [
      '#type' => 'textfield',
      '#title' => 'Label',
      '#required' => TRUE,
    ];

    $form['field_type'] = [
      '#type' => 'hidden',
      '#value' => $fieldType,
    ];

    $form['content_type'] = [
      '#type' => 'hidden',
      '#value' => $contentTypeId,
    ];

    // If the field type is a simple select field.
    if ($fieldType == 'select') {
      $form['select_list_items'] = [
        '#type' => 'textarea',
        '#title' => 'Allowed values list',
        '#suffix' => 'The possible values this field can contain. Enter one value per line, in the format key|label.
        <br>The key is the stored value. The label will be used in displayed values and edit forms.',
        '#required' => TRUE,
      ];
    }

    // If the field type is a reference select field.
    if ($fieldType == 'ref_select') {
      $form['select_ref_type'] = [
        '#type' => 'radios',
        '#title' => 'Select Reference Type',
        '#options' => [
          '0' => $this->t('Content'),
          '1' => $this->t('Taxonomies'),
        ],
      ];

      // Gets all the vocabularies.
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
      $vocabularies_opt = [];

      foreach ($vocabularies as $id => $voc) {
        $vocabularies_opt[$id] = $voc->label();
      }

      $form['select_list_ref_taxonomy'] = [
        '#type' => 'radios',
        '#title' => 'Vocabularies',
        '#options' => $vocabularies_opt,
        '#states' => [
          'visible' => [
            ':input[name="select_ref_type"]' => ['value' => '1'],
          ],
          'required' => [
            ':input[name="select_ref_type"]' => ['value' => '1'],
          ],
        ],
      ];

      // Gets all the content types.
      $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      $types_opt = [];

      foreach ($types as $type) {
        $types_opt[$type->get('type')] = $type->get('name');
      }

      $form['select_list_ref_content'] = [
        '#type' => 'radios',
        '#title' => 'Content Types',
        '#options' => $types_opt,
        '#states' => [
          'visible' => [
            ':input[name="select_ref_type"]' => ['value' => '0'],
          ],
          'required' => [
            ':input[name="select_ref_type"]' => ['value' => '0'],
          ],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit modal form'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
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
    $values = $form_state->getValues();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#dependent_dropdown_new_field_settings', $form));
    }
    elseif ($values['field_type'] == 'ref_select' && !isset($values['select_list_ref_taxonomy']) && !isset($values['select_list_ref_content'])) {
      $response->addCommand(new OpenModalDialogCommand("Failed!", 'Please Fill All The Fields Correctly!.', ['width' => 800]));
    }
    else {
      $values = $form_state->getValues();

      $dependent_dropdown_config = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');
      $fieldMachineName = preg_replace('/\s+/', '_', strtolower($values['field_label']));
      $fieldNames = [];

      foreach ($dependent_dropdown_config[$values['content_type']] as $key => $value) {
        $fieldNames[$key] = $key;
      }

      // Check if the field with this machine name already exists.
      if (in_array($fieldMachineName, $fieldNames)) {
        $response->addCommand(new OpenModalDialogCommand("Failed!", 'Sorry the field already exists.', ['width' => 800]));
        return $response;
      }

      if (isset($values['field_label']) && $values['field_label'] != '') {
        $dependent_dropdown_config[$values['content_type']][$fieldMachineName] = [
          'field_type' => $values['field_type'],
          'field_label' => $values['field_label'],
          'field_select_values' => '',
          'field_ref_type' => '',
        ];

        // If the field is a simple select list.
        if (isset($values['select_list_items'])) {
          $dependent_dropdown_config[$values['content_type']][$fieldMachineName] = [
            'field_type' => $values['field_type'],
            'field_label' => $values['field_label'],
            'field_select_values' => $values['select_list_items'],
            'field_ref_type' => '',
          ];
        }

        // If the field is a reference select list.
        if (isset($values['select_list_ref_taxonomy']) || isset($values['select_list_ref_content'])) {
          if (isset($values['select_list_ref_taxonomy'])) {
            $dependent_dropdown_config[$values['content_type']][$fieldMachineName] = [
              'field_type' => $values['field_type'],
              'field_label' => $values['field_label'],
              'field_select_values' => $values['select_list_ref_taxonomy'],
              'field_ref_type' => 'taxonomy',
            ];
          }
          else {
            $dependent_dropdown_config[$values['content_type']][$fieldMachineName] = [
              'field_type' => $values['field_type'],
              'field_label' => $values['field_label'],
              'field_select_values' => $values['select_list_ref_content'],
              'field_ref_type' => 'content',
            ];
          }
        }

        $this->configFactory->getEditable('dependent_dropdown.elements')
          ->set('dependent_dropdown_elements', $dependent_dropdown_config)
          ->save();
      }

      $response->addCommand(new OpenModalDialogCommand("Success!", 'The new field was added to the form.', ['width' => 800]));
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
