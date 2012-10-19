<?php

/**
 * @file
 * Definition of Drupal\entityreference\Plugin\field\widget\AutocompleteWidget.
 */

namespace Drupal\entityreference\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;

/**
 * Plugin implementation of the 'entityreference autocomplete' widget.
 *
 * @todo: Check if the following statement is still correct
 * The autocomplete path doesn't have a default here, because it's not the
 * the two widgets, and the Field API doesn't update default settings when
 * the widget changes.
 *
 * @Plugin(
 *   id = "entityreference_autocomplete",
 *   module = "entityreference",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "entityreference"
 *   },
 *   settings = {
 *     "match_operator" = "CONTAINS",
 *     "size" = 60,
 *     "path" = ""
 *   }
 * )
 */
class AutocompleteWidget extends WidgetBase {

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::settingsForm().
   */
  public function settingsForm(array $form, array &$form_state) {
    $widget = $instance['widget'];
    $settings = $widget['settings'] + field_info_widget_settings($widget['type']);

    $form['match_operator'] = array(
      '#type' => 'select',
      '#title' => t('Autocomplete matching'),
      '#default_value' => $settings['match_operator'],
      '#options' => array(
        'STARTS_WITH' => t('Starts with'),
        'CONTAINS' => t('Contains'),
      ),
      '#description' => t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of nodes.'),
    );
    $form['size'] = array(
      '#type' => 'textfield',
      '#title' => t('Size of textfield'),
      '#default_value' => $settings['size'],
      '#element_validate' => array('form_validate_number'),
      // Minimum value for form_validate_number().
      '#min' => 1,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::formElement().
   */
  public function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {
    $entity_type = $instance['entity_type'];
    $entity = isset($element['#entity']) ? $element['#entity'] : NULL;
    $handler = entityreference_get_selection_handler($field, $instance, $entity_type, $entity);

    // We let the Field API handles multiple values for us, only take
    // care of the one matching our delta.
    if (isset($items[$delta])) {
      $items = array($items[$delta]);
    }
    else {
      $items = array();
    }

    $entity_ids = array();
    $entity_labels = array();

    // Build an array of entities ID.
    foreach ($items as $item) {
      $entity_ids[] = $item['target_id'];
    }

    // Load those entities and loop through them to extract their labels.
    $entities = entity_load_multiple($field['settings']['target_type'], $entity_ids);

    foreach ($entities as $entity_id => $entity_item) {
      $label = $entity_item->label();
      $key = "$label ($entity_id)";
      // Labels containing commas or quotes must be wrapped in quotes.
      if (strpos($key, ',') !== FALSE || strpos($key, '"') !== FALSE) {
        $key = '"' . str_replace('"', '""', $key) . '"';
      }
      $entity_labels[] = $key;
    }

    // Prepare the autocomplete path.
    $autocomplete_path = !empty($instance['widget']['settings']['path']) ? $instance['widget']['settings']['path'] : 'entityreference/autocomplete/single';

    $autocomplete_path .= '/' . $field['field_name'] . '/' . $instance['entity_type'] . '/' . $instance['bundle'] . '/';
    // Use <NULL> as a placeholder in the URL when we don't have an entity.
    // Most webservers collapse two consecutive slashes.
    $id = 'NULL';
    if ($entity) {
      if ($eid = $entity->id()) {
        $id = $eid;
      }
    }
    $autocomplete_path .= $id;

    $element += array(
      '#type' => 'textfield',
      '#maxlength' => 1024,
      '#default_value' => implode(', ', $entity_labels),
      '#autocomplete_path' => $autocomplete_path,
      '#size' => $instance['widget']['settings']['size'],
      '#element_validate' => array('_entityreference_autocomplete_validate'),
    );
    return array('target_id' => $element);
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::errorElement().
   */
  public function errorElement(array $element, array $error, array $form, array &$form_state) {
    return $element['target_id'];
  }
}
