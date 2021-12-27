<?php

namespace Drupal\commerce_user_points\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\node\Entity\Node;

/**
 * Provides the CommerceUserPoints.
 *
 * @CommerceCheckoutPane(
 *   id = "coupons",
 *   label = @Translation("Redeem Wallet Money"),
 *   default_step = "order_information",
 * )
 */
class CommerceUserPoints extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * Default Configuration.
   *
   * @return array
   *   Description of the return value, which is a array.
   */
  public function defaultConfiguration() {
    return [
      'single_coupon' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * Build Configuration Summary.
   *
   * @return string
   *   Description of the return value, which is a string.
   */
  public function buildConfigurationSummary() {
    $summary = !empty($this->configuration['single_coupon']) ? $this->t('One time userpoints: Yes') : $this->t('One time userpoints: No');
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * Configuration Form.
   *
   * @param array $form
   *   Array $form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state.
   *
   * @return array
   *   Description of the return value, which is a array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['single_coupon'] = [
      '#type' => 'checkbox',
      '#attributes' => ['disabled' => 'disabled'],
      '#title' => $this->t('One time userpoints on Order?'),
      '#description' => $this->t('User can enter only one time userpoints on order.'),
      '#default_value' => $this->configuration['single_coupon'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Configuration Form Submission.
   *
   * @param array $form
   *   Array $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['single_coupon'] = !empty($values['single_coupon']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Build Pane Form.
   *
   * @param array $pane_form
   *   Array $pane_form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state.
   * @param array $complete_form
   *   Array complete_form.
   *
   * @return array
   *   Description of the return value, which is a array.
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {

    $arrNidPoints = $totalUsablePoints = [];

    $user = \Drupal::currentUser();

    $orderAdjustment = $this->order->getAdjustments();

    $flagPointsApplied = FALSE;

    foreach ($orderAdjustment as $adjustmentValue) {
      if ($adjustmentValue->getType() == 'custom') {
        $flagPointsApplied = TRUE;
      }
    }

    if (!$flagPointsApplied && !empty($user->id())) {
      // The options to display in our form radio buttons.
      $options = [
        '0' => t("Don't use points"),
        '1' => t('Use all usable points'),
        '2' => t('Use Specific points'),
      ];

      // Get all valid user points.
      $arrNidPoints = $this->calculateUsablePoints();

      $totalUsablePoints = round($arrNidPoints['total_usable_points']);

      $pane_form['user_points_redemption_type'] = [
        '#type' => 'radios',
        '#title' => t('User points Redeem'),
        '#options' => $options,
        '#description' => t('You have points at the moment') . " " . $totalUsablePoints,
        '#default_value' => '0',
      ];

      $pane_form['user_points_redemption'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Points'),
        '#default_value' => '',
        '#required' => FALSE,
      ];

      $pane_form['user_points_redemption']['#states'] = [
        'visible' => [
          ':input[name="coupons[user_points_redemption_type]"]' => ['value' => '2'],
        ],
      ];
    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   *
   * Validate Pane Form.
   *
   * @param array $pane_form
   *   FormStateInterface $form_state, $complete_form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state, $complete_form.
   * @param array $complete_form
   *   FormStateInterface $form_state, $complete_form.
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {

    $arrNidPoints = $totalUsablePoints = [];

    $values = $form_state->getValue($pane_form['#parents']);
    $thresholdValue = \Drupal::config('commerce_user_points.settings')->get('threshold_value');

    if (isset($values['user_points_redemption_type']) && !empty($values['user_points_redemption_type'])) {
      switch ($values['user_points_redemption_type']) {
        case '2':

          // Check if value is numeric.
          if (!is_numeric($values['user_points_redemption'])) {
            $form_state->setError($pane_form, $this->t('Please add numeric value for Points.'));
          }

          // Get all valid user points.
          $arrNidPoints = $this->calculateUsablePoints();
          $totalUsablePoints = round($arrNidPoints['total_usable_points']);

          if ($totalUsablePoints < $thresholdValue) {
            $form_state->setError($pane_form, $this->t('Curently you have @totalUsablePoints point(s) in your account. You can utilize points after you reached to @thresholdValue points.', ['@totalUsablePoints' => $totalUsablePoints, '@thresholdValue' => $thresholdValue]));
          }

          if ($this->order->hasItems()) {
            foreach ($this->order->getItems() as $orderItem) {
              $arrNidPoints = $orderItem->getTotalPrice();
              if ($values['user_points_redemption'] > $totalUsablePoints) {
                $form_state->setError($pane_form, $this->t('You can maximum use @totalUsablePoints for points.', ['@totalUsablePoints' => number_format($totalUsablePoints)]));
              }
            }
          }

          break;

        case '1':

          // Get all valid user points.
          $arrNidPoints = $this->calculateUsablePoints();
          $totalUsablePoints = round($arrNidPoints['total_usable_points']);

          if ($totalUsablePoints < $thresholdValue) {
            $form_state->setError($pane_form, $this->t('Curently you have @totalUsablePoints point(s) in your account. You can utilize points after you reached to @thresholdValue points.', ['@totalUsablePoints' => $totalUsablePoints, '@thresholdValue' => $thresholdValue]));
          }

          break;

        default:
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Submit Pane Form.
   *
   * @param array $pane_form
   *   FormStateInterface $form_state, array $complete_form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface $form_state, array $complete_form.
   * @param array $complete_form
   *   FormStateInterface $form_state, array $complete_form.
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $arrNidPoints = $totalUsablePoints = [];
    $values = $form_state->getValue($pane_form['#parents']);

    $paymentInformation = $form_state->getValue('payment_information');

    if (isset($values['user_points_redemption_type']) && !empty($values['user_points_redemption_type'])) {

      $arrNidPoints = $this->calculateUsablePoints();

      // Calculate usable points based on user selected value.
      switch ($values['user_points_redemption_type']) {
        case '2':
          $totalUsablePoints = $values['user_points_redemption'];
          break;

        case '1':
          // Get all valid user points.
          $totalUsablePoints = round($arrNidPoints['total_usable_points']);
          break;

        default:
          $totalUsablePoints = '0';
          break;
      }

      if (!empty($totalUsablePoints)) {

        $orderItemTotal = 0;

        if ($this->order->hasItems()) {
          foreach ($this->order->getItems() as $orderItem) {
            $orderItemTotal += $orderItem->getTotalPrice()->getNumber();
          }
        }

        if ($orderItemTotal < $totalUsablePoints) {
          $totalUsablePoints = $orderItemTotal;
        }

        foreach ($this->order->getItems() as $orderItem) {
          $purchasedEntity = $orderItem->getPurchasedEntity();
          $productId = $purchasedEntity->get('product_id')->getString();
          $product = Product::load($productId);
          // To get the store details for currency code.
          $store = Store::load(reset($product->getStoreIds()));
        }

        // Create adjustment object for current order.
        $adjustments = new Adjustment([
          'type' => 'custom',
          'label' => 'User Points Deduction',
          'amount' => new Price('-' . $totalUsablePoints, $store->get('default_currency')->getString()),
        ]);

        $userPointsNids = $arrNidPoints['user_points_nids'];

        $deductUserPoints = $this->deductUserPoints($userPointsNids, $totalUsablePoints);
        if ($deductUserPoints) {
          // Add adjustment to order and save.
          $this->order->addAdjustment($adjustments);
          $this->order->save();
        }
      }
    }
  }

  /**
   * Deduct user points from nodes $userPointsNids $totalUsablePoints.
   *
   * @param array $userPointsNids
   *   array $totalUsablePoints.
   * @param string $totalUsablePoints
   *   string $totalUsablePoints.
   *
   * @return bool
   *   Description of the return value, which is a boolean.
   */
  public function deductUserPoints(array $userPointsNids, $totalUsablePoints) {

    $updatedPoints = 0;

    $calculatedRemainingPoints = $totalUsablePoints;

    // Get all valid user points
    // $nodes = entity_load_multiple('node', $userPointsNids);.
    $nodes = Node::loadMultiple($userPointsNids);

    foreach ($nodes as $key => $node) {

      $earnedNodePoints = $node->get('field_earned_points')->getString();
      $usedNodePoints = $node->get('field_used_points')->getString();
      $availableNodePoints = $earnedNodePoints - $usedNodePoints;

      $nextDeductPoints = $calculatedRemainingPoints - $availableNodePoints;
      if ($updatedPoints < $totalUsablePoints) {
        if ($nextDeductPoints > 0) {
          $updatedPoints += $availableNodePoints;
          $calculatedRemainingPoints = $nextDeductPoints;
          $nodeUpdatePoints = $usedNodePoints + $availableNodePoints;
          $node->set('field_used_points', $nodeUpdatePoints);
          $node->save();
        }
        else {
          $updatedPoints += $calculatedRemainingPoints;
          $nodeUpdatePoints = $usedNodePoints + $calculatedRemainingPoints;
          $node->set('field_used_points', $nodeUpdatePoints);
          $node->save();
        }
      }
    }

    return TRUE;
  }

  /**
   * Calculate user available points.
   *
   * @return arrNidPoints
   *   Description of the return value, which is a array.
   */
  public function calculateUsablePoints() {

    // Get all valid user points.
    $user = \Drupal::currentUser();

    $bundle = 'user_points';
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $query->condition('uid', $user->id());
    $query->condition('field_point_status.value', '1', '=');
    $query->condition('field_validity_date.value', gmdate('Y-m-d'), '>=');
    $query->sort('field_validity_date.value', 'ASC');
    $entityIds = $query->execute();

    // $nodes = entity_load_multiple('node', $entityIds);
    $nodes = Node::loadMultiple($entityIds);

    $totalEarnedPoints = 0;
    $totalUsedPoints = 0;
    $totalUsablePoints = 0;

    foreach ($nodes as $nodeId => $nodeObject) {
      $totalEarnedPoints += $nodeObject->get('field_earned_points')->getString();
      $totalUsedPoints += $nodeObject->get('field_used_points')->getString();
    }

    // Total usable points by logged in user.
    $totalUsablePoints = $totalEarnedPoints - $totalUsedPoints;

    $arrNidPoints = [];

    $arrNidPoints['total_usable_points'] = round($totalUsablePoints);
    $arrNidPoints['user_points_nids'] = $entityIds;

    return $arrNidPoints;
  }

}
