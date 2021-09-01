<?php

namespace Drupal\dependent_dropdown\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Provides route responses for the DependentDropdownController.
 */
class DependentDropdownController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\CacheBackendInterface
   */
  protected $cacheRender;

  /**
   * Creates a DependentPage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\CacheBackendInterface $cacheRender
   *   The cache render service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactory $config_factory, CacheBackendInterface $cacheRender) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
    $this->cacheRender = $cacheRender;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('cache.render')
    );
  }

  /**
   * Lists all the content types on the dependent dropdown homepage.
   */
  public function listAllContentTypes() {

    // Get all content types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $node_type_markup = '<h1>Content types:</h1>';
    $node_type_markup .= '<ul>';

    foreach ($node_types as $type) {
      $node_type_markup .= '<li>' . $type->get('name') . ' <a href="/admin/config/dependent-dropdown/fields/' . $type->get('type') . ' "> add </a>' . '</li>';
    }

    $node_type_markup .= '</ul>';
    return [
      '#markup' => $node_type_markup,
    ];
  }

  /**
   * Function to delete fields create via hook_alter_form.
   */
  public function deleteHookElement(string $contentType, string $fieldType, string $fieldName) {

    $dependent_dropdown_elements = $this->configFactory->get('dependent_dropdown.elements')->get('dependent_dropdown_elements');
    unset($dependent_dropdown_elements[$contentType][$fieldName]);

    $this->configFactory->getEditable('dependent_dropdown.elements')
      ->set('dependent_dropdown_elements', $dependent_dropdown_elements)
      ->save();

    $this->cacheRender->invalidateAll();

    return new RedirectResponse('/admin/config/dependent-dropdown/fields/' . $contentType);

  }

  /**
   * Function to unset a select field settings.
   */
  public function unsetSelectField(string $contentType, string $fieldName) {

    $dependent_dropdown_select = $this->configFactory->get('dependent_dropdown.select')->get('dependent_dropdown_select');
    unset($dependent_dropdown_select[$contentType][$fieldName]);

    $this->configFactory->getEditable('dependent_dropdown.select')
      ->set('dependent_dropdown_select', $dependent_dropdown_select)
      ->save();

    $this->cacheRender->invalidateAll();

    return new RedirectResponse('/admin/config/dependent-dropdown/fields/' . $contentType);

  }

  /**
   * Function to unset a number field settings.
   */
  public function unsetNumberField(string $contentType, string $fieldName) {

    $dependent_dropdown_calculate = $this->configFactory->get('dependent_dropdown.calculate')->get('dependent_dropdown_calculate');
    unset($dependent_dropdown_calculate[$contentType][$fieldName]);

    $this->configFactory->getEditable('dependent_dropdown.calculate')
      ->set('dependent_dropdown_calculate', $dependent_dropdown_calculate)
      ->save();

    $this->cacheRender->invalidateAll();

    return new RedirectResponse('/admin/config/dependent-dropdown/fields/' . $contentType);

  }

}
