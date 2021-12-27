<?php

namespace Drupal\commerce_user_points\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\Entity\Node;

/**
 * Class OrderCompleteSubscriber.
 *
 * @package Drupal\commerce_user_points
 */
class OrderCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   About this.
   */
  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * Get Subscribed Events.
   *
   * @return events
   *   Description of the return value, which is a events.
   */
  public static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = ['orderCompleteHandler'];
    return $events;
  }

  /**
   * This method is called whenever the commerce_order.place.
   *
   *   Post_transition.
   *
   *   Event is dispatched.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   Event.
   */
  public function orderCompleteHandler(WorkflowTransitionEvent $event) {

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $userPoints = 0;
    $currentAdjustments = $order->getAdjustments();
    if (!empty($currentAdjustments)) {
      foreach ($currentAdjustments as $adjustmentValue) {
        // @todo - update
        if ($adjustmentValue->getLabel() == 'User Points Deduction') {
          $pointsAmount = $adjustmentValue->getAmount();
          $userPoints = abs($pointsAmount->getNumber());
        }
      }
    }

    $orderUid = $order->getCustomerId();
    $orderEmail = $order->getEmail();
    $orderSubtotal = $order->getSubtotalPrice();
    $orderTotal = $order->getTotalPrice();
    $totalOrderAmount = $orderSubtotal->getNumber();
    $orderPointDiscount = \Drupal::config('commerce_user_points.settings')->get('order_point_discount');
    $dayPointDiscount = \Drupal::config('commerce_user_points.settings')->get('day_point_discount');
    $datePointDiscount = \Drupal::config('commerce_user_points.settings')->get('date_point_discount');

    // Get discount percentage.
    $orderAppliedDiscount = $orderPointDiscount;

    // Update discount percentage if day is same.
    if (gmdate('N') == $dayPointDiscount) {
      $orderAppliedDiscount = $datePointDiscount;
    }

    // Built array to save "user_point" node.
    $arrNode = [
      'type' => 'user_points',
      'title' => "New order " . $order->getOrderNumber() . " for " . $orderEmail . ", UID: " . $orderUid,
      'uid' => $orderUid,
      'field_earned_points' => round(($totalOrderAmount * $orderAppliedDiscount) / 100),
      'field_points_acquisition_date' => gmdate('Y-m-d'),
      'field_point_status' => 1,
      'field_point_type' => 'shopping',
      'field_used_points' => 0,
      'field_validity_date' => gmdate('Y-m-d', strtotime('+1 years')),
    ];

    // Save new node.
    $nodeEntity = Node::create($arrNode);
    $nodeEntity->save();
  }

}
