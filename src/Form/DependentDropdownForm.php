<?php

namespace Drupal\dependent_dropdown\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * The main form for each content type settings.
 */
class DependentDropdownForm extends ConfigFormBase {

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
   * Creates a DependentDropdownForm object.
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
    return 'dependent_dropdown_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dependent_dropdown.select',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $contentType = NULL) {

    $dependent_dropdown_config = $this->configFactory->get('dependent_dropdown.select')->get('dependent_dropdown_select');
    $dependent_dropdown_calculate = $this->configFactory->get('dependent_dropdown.calculate')->get('dependent_dropdown_calculate');

    $entity_type_id = 'node';
    $bundle = $contentType;
    $form_mode = 'default';
    $counter = 0;
    $select_list = [];
    $number_list = [];

    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_type_id . '.' . $bundle . '.' . $form_mode);

    // Get all the select fields from the content type created via drupal.
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {

        $specific_widget_type = $form_display->getComponent($field_name);
        if (isset($specific_widget_type['type'])) {
          if ($specific_widget_type['type'] == 'options_select') {
            $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
            $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
            $select_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
            $counter++;
          }
        }
      }
    }

    // Get all the number fields from the content type created via drupal.
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {

        $specific_widget_type = $form_display->getComponent($field_name);
        if (isset($specific_widget_type['type'])) {
          if ($specific_widget_type['type'] == 'number') {
            $bundleFields[$entity_type_id][$field_name]['type'] = $field_definition->getType();
            $bundleFields[$entity_type_id][$field_name]['label'] = $field_definition->getLabel();
            $number_list[$field_name] = $bundleFields[$entity_type_id][$field_name]['label'];
            $counter++;
          }
        }
      }
    }

    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $contentTypeName = '';

    // Get the name of the content type via its machine name.
    foreach ($types as $type) {
      if ($type->get('type') == $bundle) {
        $contentTypeName = $type->get('name');
      }
    }

    $form['#prefix'] = '<h1>Content type: ' . $contentTypeName . '</h1>';

    $hook_all_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

    // Get all the select fields from the content type created via hook alter.
    if (isset($hook_all_elements)) {
      foreach ($hook_all_elements as $key => $values) {
        if ($key == $bundle) {
          foreach ($values as $key_two => $values_two) {
            if ($values_two['field_type'] == 'select' || $values_two["field_type"] == 'ref_select') {
              $select_list[$key_two] = $values_two['field_label'];
            }
          }
        }
      }
    }

    $form['dependent_dropdown_content_type'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];

    foreach ($select_list as $key => $type) {
      $route_parameters = [
        'contentType' => $contentType,
        'fieldName' => $key,
      ];

      // Show all the select fields from the content type as buttons.
      $form[$key] = [
        '#type' => 'link',
        '#title' => $type . ' (Add)',
        '#url' => Url::fromRoute('dependent_dropdown.edit_settings', $route_parameters),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
          ],
        ],
      ];
    }

    foreach ($number_list as $key => $type) {
      $route_parameters = [
        'contentType' => $contentType,
        'fieldName' => $key,
      ];

      // Show all the number fields from the content type as buttons.
      $form[$key] = [
        '#type' => 'link',
        '#title' => $type . ' (Add)',
        '#url' => Url::fromRoute('dependent_dropdown.edit_settings', $route_parameters),
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
          ],
        ],
      ];
    }

    $route_parameters = [
      'contentType' => $contentType,
    ];

    // Add new fields button.
    $form['add-fields'] = [
      '#type' => 'link',
      '#title' => 'Add Fields',
      '#url' => Url::fromRoute('dependent_dropdown.add_fields', $route_parameters),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'button',
        ],
      ],
    ];

    // Attach the library for pop-up dialogs/modals.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $report = '<h1>Report:</h1>';

    $current_path = \Drupal::service('path.current')->getPath();
    $url_object = \Drupal::service('path.validator')->getUrlIfValid($current_path);
    $route_name = $url_object->getRouteName();
    $route_parameters = $url_object->getrouteParameters();
    $route_node_parameter = '';

    if (isset($route_parameters['node'])) {
      $route_node_parameter = $route_parameters['node'];
      $route_node_parameter_type = 'node';
    }
    
    if (isset($route_parameters['node_type'])) {
      $route_node_parameter = $route_parameters['node_type'];
      $route_node_parameter_type = 'node_type';
    }

    // Show names of fields created via hook alter.
    if (isset($hook_all_elements[$contentType]) && !empty($hook_all_elements[$contentType])) {
      $report .= '<h2>Hook Select Elements:</h2>';

      foreach ($hook_all_elements[$contentType] as $name => $values) {
        if ($values["field_type"] == 'select' || $values["field_type"] == 'ref_select') {
          $report .= '<h4>' . $values['field_label'] . ' <a href="' . base_path() . 'admin/config/delete-hook-element/' . $contentType . '/' . $values["field_type"] . '/' . $name . '/' . $route_name . '/' . $route_node_parameter  . '/' . $route_node_parameter_type . '" > Delete</a></h4>';
        }
      }

      $report .= '<h2>Hook Number Elements:</h2>';

      foreach ($hook_all_elements[$contentType] as $name => $values) {
        if ($values["field_type"] == 'number') {
          $report .= '<h4>' . $values['field_label'] . ' <a href="' . base_path() . 'admin/config/delete-hook-element/' . $contentType . '/' . $values["field_type"] . '/' . $name . '/' . $route_name . '/' . $route_node_parameter  . '/' . $route_node_parameter_type . '" > Delete</a></h4>';
        }
      }
    }

    // Show details of all select fields.
    if (isset($dependent_dropdown_config[$contentType]) && !empty($dependent_dropdown_config[$contentType])) {
      $report .= '<h2>Select Elements Details:</h2>';

      foreach ($dependent_dropdown_config[$contentType] as $name => $values) {
        if (isset($select_list[$name])) {
          $report .= '<h4>' . $select_list[$name] . ' <a href="' . base_path() . 'admin/config/unset-select-field/' . $contentType . '/' . $name . '/' . $route_name . '/' . $route_node_parameter  . '/' . $route_node_parameter_type . '" > Reset</a></h4>';
          $report .= '<p>' . '<b>Rest Export Paths:</b> ' . $values['dependent_dropdown_url'] . '</p>';
          $report .= '<p>' . '<b>Dependent Field:</b> ' . $select_list[$values['dependent_dropdown_dependent']] . '</p>';
        }
      }
    }

    // Show details of all number fields.
    $hook_number_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');

    if (isset($hook_number_elements)) {
      foreach ($hook_number_elements as $key => $values) {
        if ($key == $contentType) {
          foreach ($values as $key_two => $values_two) {
            if ($values_two['field_type'] == 'number') {
              $number_list[$key_two] = $values_two['field_label'];
            }
          }
        }
      }
    }

    if (isset($dependent_dropdown_calculate[$contentType]) && !empty($dependent_dropdown_calculate[$contentType])) {
      $report .= '<h2>Number Elements Details:</h2>';

      foreach ($dependent_dropdown_calculate[$contentType] as $name => $values) {
        if (isset($number_list[$name])) {
          $report .= '<h4>' . $number_list[$name] . ' <a href="' . base_path() . 'admin/config/unset-number-field/' . $contentType . '/' . $name . '/' . $route_name . '/' . $route_node_parameter  . '/' . $route_node_parameter_type . '" > Reset</a></h4>';
          $report .= '<p>' . '<b>Depends On Field 1:</b> ' . $number_list[$values['dependent_dropdown_number1']] . '</p>';
          $report .= '<p>' . '<b>Depends On Field 2:</b> ' . $number_list[$values['dependent_dropdown_number2']] . '</p>';
          $report .= '<p>' . '<b>Operator:</b> ' . $values['dependent_dropdown_operator'] . '</p>';
        }
      }
    }

    $form['#suffix'] = $report;

    return $form;

  }

}
