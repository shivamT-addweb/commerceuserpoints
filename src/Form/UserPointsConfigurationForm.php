<?php

namespace Drupal\commerce_user_points\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class UserPointsConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   *
   * Set Form ID.
   *
   * @return string
   *   Description of the return value, which is a string.
   */
  public function getFormId() {
    return 'commerce_user_points_admin_settings';
  }

  /**
   * {@inheritdoc}
   *
   * Get Editable Config Names.
   *
   * @return string
   *   Description of the return value, which is a string.
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_user_points.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Build Config Form.
   *
   * @param array $form
   *   FormStateInterface $form_state, Request $request.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request $request.
   *
   * @return array|Object
   *   Description of the return value, which is a array|object.
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('commerce_user_points.settings');

    // User registration points.
    $form['user_register_points'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User registration points'),
      '#attributes' => [
        'type' => 'number',
      ],
      '#default_value' => $config->get('user_register_points'),
    ];

    // Default Percentage.
    $form['order_point_discount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Percentage'),
      '#attributes' => [
        'type' => 'number',
      ],
      '#default_value' => $config->get('order_point_discount'),
    ];

    $form['date_discount'] = [
      '#type' => 'details',
      '#title' => t('Advanced settings'),
      '#description' => t('Select day on which specific discount is applicable.'),
      '#open' => TRUE,
    ];

    // @todo - make field dynamic
    $options = [
      '1' => t('Monday'),
      '2' => t('Tuesday'),
      '3' => t('Wednesday'),
      '4' => t('Thurday'),
      '5' => t('Friday'),
      '6' => t('Saturday'),
      '7' => t('Sunday'),
    ];

    $form['date_discount']['day_point_discount'] = [
      '#type' => 'select',
      '#title' => t('Day'),
      '#options' => $options,
      '#description' => t('Add points to select'),
      '#default_value' => $config->get('day_point_discount'),
    ];

    $form['date_discount']['date_point_discount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Day Percentage'),
      '#attributes' => [
        'type' => 'number',
      ],
      '#default_value' => $config->get('date_point_discount'),
    ];
    $form['maximum_value']['threshold_value'] = [
      '#type' => 'textfield',
      '#title' => t('Threshold value'),
      '#description' => t('Minimum level of points to use'),
      '#default_value' => $config->get('threshold_value'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Save Config Form Data.
   *
   * @param array $form
   *   FormStateInterface $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->config('commerce_user_points.settings')
      ->set('user_register_points', $values['user_register_points'])
      ->set('order_point_discount', $values['order_point_discount'])
      ->set('day_point_discount', $values['day_point_discount'])
      ->set('date_point_discount', $values['date_point_discount'])
      ->set('threshold_value', $values['threshold_value'])
      ->save();
  }

}
