<?php

namespace Drupal\dependent_dropdown\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\views\Views;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * This form is shown in modal for editing field settings.
 */
class EditSettings extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Creates a EditSettings object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactory $config_factory, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dependent_dropdown_edit_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state, $contentTypeId = NULL, $removeField = NULL) {
    $form['#prefix'] = '<div id="dependent_dropdown_edit_settings">';
    $form['#suffix'] = '</div>';

    $field_list = [];
    $field_list[''] = 'Select';

    $entity_type_id = 'node';
    $bundle = $contentTypeId;
    $form_mode = 'default';
    $counter = 0;
    $field_type = '';

    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_type_id . '.' . $bundle . '.' . $form_mode);

    // Get type of the edited field if it is created via drupal.
    $field_type = $form_display->getComponent($removeField)['type'];

    // Else get type of the edited field if it is created via hook form alter.
    $hook_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

    if (isset($hook_elements)) {
      foreach ($hook_elements as $key => $values) {
        if ($key == $bundle) {
          foreach ($values as $key_two => $values_two) {
            if ($values_two['field_type'] == 'select' || $values_two['field_type'] == 'ref_select') {
              if (!isset($field_type)) {
                $field_type = $values_two['field_type'];
              }
            }
          }
        }
      }
    }

    // Content type name to which the field belongs.
    $form['dependent_dropdown_content_type'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];

    // The name of the field which settings to be edited.
    $form['dependent_dropdown_remove_field'] = [
      '#type' => 'hidden',
      '#value' => $removeField,
    ];

    // The settings to show if the field type is select.
    if ($field_type == 'options_select' || $field_type == 'select' || $field_type == 'ref_select') {

      // Get all the select fields from the content type created via drupal.
      foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
        if (!empty($field_definition->getTargetBundle())) {

          $specific_widget_type = $form_display->getComponent($field_name);
          if ($specific_widget_type['type'] == 'options_select' && $field_name != $removeField) {
            $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
            $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
            $field_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
            $counter++;
          }
        }
      }

      // Get rest paths from all the views.
      $views = Views::getAllViews();
      $rest_views = [];
      $rest_views[''] = 'Select';

      foreach ($views as $view_object) {
        if (!isset($view_object->disabled) || !$view_object->disabled) {
          foreach ($view_object->get('display') as $views_display) {
            $title = $views_display['display_title'];
            if ($views_display['display_plugin'] == 'rest_export') {
              $rest_views[$views_display['display_options']['path']] = $view_object->get('label') . ' ' . $title;
            }
          }
        }
      }

      $dependent_dropdown_config = $this->configFactory->get('dependent_dropdown.select')->get('dependent_dropdown_select');

      // List of the rest paths.
      $form['dependent_dropdown_paths'] = [
        '#type' => 'select',
        '#title' => 'Rest Export Paths',
        '#options' => $rest_views,
        '#default_value' => $dependent_dropdown_config[$bundle][$removeField]['dependent_dropdown_url'] ?: '',
        '#required' => TRUE,
      ];

      // List of all select fields except the edited field.
      $form['dependent_dropdown_dependent'] = [
        '#type' => 'select',
        '#title' => 'Dependent',
        '#options' => $field_list,
        '#default_value' => $dependent_dropdown_config[$bundle][$removeField]['dependent_dropdown_dependent'] ?: '',
        '#required' => TRUE,
      ];

      // The status messages that will contain any form errors.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];

      // The name of the field which settings to be edited.
      $form['dependent_dropdown_depends_on'] = [
        '#type' => 'hidden',
        '#value' => $removeField,
      ];

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

    }

    // The settings to show if the field type is number.
    if ($field_type == 'number') {

      // Get all the number fields from the content type created via drupal.
      foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
        if (!empty($field_definition->getTargetBundle())) {

          $specific_widget_type = $form_display->getComponent($field_name);
          if ($specific_widget_type['type'] == 'number' && $field_name != $removeField) {
            $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
            $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
            $field_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
            $counter++;
          }
        }
      }

      // Get all number fields from content type created via hook form alter.
      $hook_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

      if (isset($hook_elements)) {
        foreach ($hook_elements as $key => $values) {
          if ($key == $bundle) {
            foreach ($values as $key_two => $values_two) {
              if ($values_two['field_type'] == 'number' && $key_two != $removeField) {
                $field_list[$key_two] = $values_two['field_label'];
              }
            }
          }
        }
      }

      $dependent_dropdown_calculate = $this->configFactory->get('dependent_dropdown.calculate')->get('dependent_dropdown_calculate');

      $form['dependent_dropdown_result'] = [
        '#type' => 'hidden',
        '#value' => $removeField,
      ];

      $form['dependent_dropdown_number1'] = [
        '#type' => 'select',
        '#title' => 'First Field',
        '#options' => $field_list,
        '#default_value' => $dependent_dropdown_calculate[$bundle][$removeField]['dependent_dropdown_number1'] ?: '',
        '#required' => TRUE,
        '#prefix' => '<div id="dependent_dropdown_number1">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => [$this, 'dependentDropdownNumber1Ajax'],
          'event' => 'change',
          'wrapper'  => 'dependent_dropdown_number2',
        ],
      ];

      $form['dependent_dropdown_number2'] = [
        '#type' => 'select',
        '#title' => 'Second Field',
        '#options' => $field_list,
        '#default_value' => $dependent_dropdown_calculate[$bundle][$removeField]['dependent_dropdown_number2'] ?: '',
        '#required' => TRUE,
        '#prefix' => '<div id="dependent_dropdown_number2">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => [$this, 'dependentDropdownNumber2Ajax'],
          'event' => 'change',
          'wrapper'  => 'dependent_dropdown_number1',
        ],
      ];

      $operator_array = [
        '' => 'Select',
        'addition' => 'Addition',
        'subtraction' => 'Subtraction',
        'multiplication' => 'Multiplication',
        'division' => 'Division',
      ];

      $form['dependent_dropdown_operator'] = [
        '#type' => 'select',
        '#title' => 'Operator',
        '#options' => $operator_array,
        '#default_value' => $dependent_dropdown_calculate[$bundle][$removeField]['dependent_dropdown_operator'] ?: '',
        '#required' => TRUE,
      ];

      // The status messages that will contain any form errors.
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];

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
    }

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#dependent_dropdown_edit_settings', $form));
    }
    else {
      $values = $form_state->getValues();

      $dependent_dropdown_config = $this->configFactory->get('dependent_dropdown.select')->get('dependent_dropdown_select');

      // Configuration to save if select field is edited.
      if (isset($values['dependent_dropdown_depends_on'])) {
        $dependent_dropdown_config[$values['dependent_dropdown_content_type']][$values['dependent_dropdown_depends_on']] = [
          'dependent_dropdown_url' => $values['dependent_dropdown_paths'],
          'dependent_dropdown_dependent' => $values['dependent_dropdown_dependent'],
        ];

        $this->configFactory->getEditable('dependent_dropdown.select')
          ->set('dependent_dropdown_select', $dependent_dropdown_config)
          ->save();
      }

      $dependent_dropdown_calculate = $this->configFactory->get('dependent_dropdown.calculate')->get('dependent_dropdown_calculate');

      // Configuration to save if number field is edited.
      if (isset($values['dependent_dropdown_result'])) {
        $dependent_dropdown_calculate[$values['dependent_dropdown_content_type']][$values['dependent_dropdown_result']] = [
          'dependent_dropdown_number1' => $values['dependent_dropdown_number1'],
          'dependent_dropdown_number2' => $values['dependent_dropdown_number2'],
          'dependent_dropdown_operator' => $values['dependent_dropdown_operator'],
        ];

        $this->configFactory->getEditable('dependent_dropdown.calculate')
          ->set('dependent_dropdown_calculate', $dependent_dropdown_calculate)
          ->save();
      }

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

  /**
   * AJAX callback handler that updates the values of first field.
   */
  public function dependentDropdownNumber1Ajax(array $form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $entity_type_id = 'node';
    $bundle = $values['dependent_dropdown_content_type'];
    $form_mode = 'default';
    $counter = 0;
    $select_list = [];
    $select_list[''] = 'Select';
    $remove_field = $values['dependent_dropdown_number1'];
    $dependent_dropdown_remove_field = $values['dependent_dropdown_remove_field'];

    // Get all the number fields from the content type created via drupal.
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {

        $form_display = $this->entityTypeManager
          ->getStorage('entity_form_display')
          ->load($entity_type_id . '.' . $bundle . '.' . $form_mode);

        $specific_widget_type = $form_display->getComponent($field_name);
        if ($specific_widget_type['type'] == 'number') {
          $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
          $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
          $select_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
          $counter++;
        }
      }
    }

    $hook_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

    // Get all number fields from the content type created via hook form alter.
    if (isset($hook_elements)) {
      foreach ($hook_elements as $key => $values) {
        if ($key == $bundle) {
          foreach ($values as $key_two => $values_two) {
            if ($values_two['field_type'] == 'number') {
              $select_list[$key_two] = $values_two['field_label'];
            }
          }
        }
      }
    }

    // Remove the edited field and the selected field from second field.
    unset($select_list[$remove_field]);
    unset($select_list[$dependent_dropdown_remove_field]);

    $form['dependent_dropdown_number2']['#options'] = $select_list;

    $form_state->setRebuild(TRUE);

    $ajaxResponse = new ajaxResponse();

    $ajaxResponse->addCommand(new ReplaceCommand("#dependent_dropdown_number2", $form['dependent_dropdown_number2']));
    return $ajaxResponse;
  }

  /**
   * AJAX callback handler that updates the values of second field.
   */
  public function dependentDropdownNumber2Ajax(array $form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $entity_type_id = 'node';
    $bundle = $values['dependent_dropdown_content_type'];
    $form_mode = 'default';
    $counter = 0;
    $select_list = [];
    $select_list[''] = 'Select';
    $remove_field = $values['dependent_dropdown_number2'];
    $dependent_dropdown_remove_field = $values['dependent_dropdown_remove_field'];

    // Get all the number fields from the content type created via drupal.
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {

        $form_display = $this->entityTypeManager
          ->getStorage('entity_form_display')
          ->load($entity_type_id . '.' . $bundle . '.' . $form_mode);

        $specific_widget_type = $form_display->getComponent($field_name);
        if ($specific_widget_type['type'] == 'number') {
          $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
          $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
          $select_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
          $counter++;
        }
      }
    }

    $hook_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

    // Get all number fields from the content type created via hook form alter.
    if (isset($hook_elements)) {
      foreach ($hook_elements as $key => $values) {
        if ($key == $bundle) {
          foreach ($values as $key_two => $values_two) {
            if ($values_two['field_type'] == 'number') {
              $select_list[$key_two] = $values_two['field_label'];
            }
          }
        }
      }
    }

    // Remove the edited field and the selected field from first field.
    unset($select_list[$remove_field]);
    unset($select_list[$dependent_dropdown_remove_field]);

    $form['dependent_dropdown_number1']['#options'] = $select_list;

    $form_state->setRebuild(TRUE);

    $ajaxResponse = new ajaxResponse();

    $ajaxResponse->addCommand(new ReplaceCommand("#dependent_dropdown_number1", $form['dependent_dropdown_number1']));
    return $ajaxResponse;
  }

}
