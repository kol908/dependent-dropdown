<?php

namespace Drupal\dependent_dropdown\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Dependent Dropdown Block.
 *
 * @Block(
 *   id = "dependent_dropdown_block",
 *   admin_label = @Translation("Dependent Dropdown Block"),
 *   category = @Translation("Dependent Dropdown"),
 * )
 */
class DependentDropdownBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $node = \Drupal::routeMatch()->getParameters();
    $current_path = \Drupal::service('path.current')->getPath();
    $url_object = \Drupal::service('path.validator')->getUrlIfValid($current_path);
    $route_name = $url_object->getRouteName();
    $route_parameters = $url_object->getrouteParameters();

    if ($route_name == 'node.add' || $route_name == 'entity.node.edit_form') {
      if ($node->get('node')) {
        $node_type = $node->get('node');
        $type = $node_type->bundle();
      } else {
        $node_type = $node->get('node_type');
        $type = $node_type->get('type');
      }

      $builtForm = \Drupal::formBuilder()->getForm('Drupal\dependent_dropdown\Form\DependentDropdownForm', $type);
      $renderArray['form'] = $builtForm;
      
      return $renderArray;
    }

    $renderArray['form'] = [];
    return $renderArray;
  }

}
