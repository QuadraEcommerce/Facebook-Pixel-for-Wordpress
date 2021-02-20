<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Integration;

defined('ABSPATH') or die('Direct access not allowed');

use FacebookAds\Object\ServerSide\Content;
use FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\Core\ServerEventFactory;

class FacebookWordpressShopQuadra extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE = 'shopquadra-plugin/quadra-ecommerce-shopquadra.php';
  const TRACKING_NAME = 'shopquadra';

  public static function injectPixelCode() {
    // Purchase

    //ray(__METHOD__ . ' called')->green();

    add_action(
      'quadra_purchase_pixel',
      array(__CLASS__, 'injectPurchaseEvent'), 11, 4);
  }

  /**
   * @param string $offerType
   * @param object|\Upsell\Models\Order $order
   * @param object|\Upsell\Models\OrderItem $item
   * @param object|\Upsell\Models\Product $product
   */
  public static function injectPurchaseEvent($offerType, $order, $item, $product) {
    // ray('injectPurchaseEvent parameters:', $offerType, $order, $item, $product)->green();

    if (FacebookPluginUtils::isInternalUser()) {
      //ray('internal user, not injecting purchase event')->green();
      // return;
    }

    $serverEvent = ServerEventFactory::safeCreateEvent(
      'Purchase',
      array(__CLASS__, 'createPurchaseEvent'),
      array($offerType, $order, $item, $product),
      self::TRACKING_NAME
    );

    $serverEvent = static::addCustomData($serverEvent, $offerType, $order);

    //ray('$serverEvent:', $serverEvent)->hide();

    FacebookServerSideEvent::getInstance()->track($serverEvent, $sendNow = true);

    $code = PixelRenderer::render(array($serverEvent), self::TRACKING_NAME);

    //ray("pixel event code: $code");

    printf("
<!-- Facebook Pixel Event Code -->
<!-- TEMPORARILY DISABLED
%s
TODO: REMOVE COMMENT TAGS -->
<!-- End Facebook Pixel Event Code -->
     ",
      $code);
  }

  /**
   * @param Event $serverEvent
   * @param string $offerType
   * @param object|\Upsell\Models\Order $order
   *
   * @return Event
   */
  private static function addCustomData($serverEvent, $offerType, $order) {
    $customData = $serverEvent->getCustomData();
    $customData->setOrderId($order->ID);
    $customData->setNumItems(1);
    $customData->addCustomProperty('offer_type', $offerType);

    $additional = $order->additional();

    foreach ($additional as $key => $value) {
      if ($key === 'team') {
        $teamConfigs = get_team_configs();
        $customData->addCustomProperty('team', $value);

        if (isset($teamConfigs[$value])) {
          $customData->addCustomProperty('team_name', $teamConfigs[$value]['full_name']);
        }
      } elseif (! isset($eventData[$key])) {
        $customData->addCustomProperty($key, $value);
      }
    }

    return $serverEvent->setCustomData($customData);
  }

  /**
   * @param string $offerType
   * @param object|\Upsell\Models\Order $order
   * @param object|\Upsell\Models\OrderItem $item
   * @param object|\Upsell\Models\Product $product
   *
   * @return array
   */
  public static function createPurchaseEvent($offerType, $order, $item, $product) {
    $additional = $order->additional();
    $sku = $additional['product_sku'];

    $content = new Content(array(
      'id'         => $sku,
      'quantity'   => $item['quantity'],
      'item_price' => $item['price'] / $item['quantity'],
    ));

    $eventData = array(
      'order_id'     => $order->ID,
      'value'        => $item['total'],
      'currency'     => 'USD',
      'contents'     => [$content],
      'content_ids'  => [$sku],
      'content_type' => 'product',
      'content_name' => get_the_title($product->ID),
    );

    $customer = $order->customer();
    $country = $customer->getMeta('billing_country');
    $firstName = $customer->getMeta('first_name');
    $lastName = $customer->getMeta('last_name');

    if (empty($firstName)) {
      $name = ServerEventFactory::splitName($customer->getMeta('full_name'));
      $firstName = $name[0];
      $lastName = $name[1];
    }

    $eventData += array_filter(array(
      'email'      => $customer->getMeta('email'),
      'first_name' => $firstName,
      'last_name'  => $lastName,
      'city'       => $customer->getMeta('billing_city'),
      'state'      => $customer->getMeta('billing_state'),
      'zip'        => $customer->getMeta('billing_zip'),
      'country'    => strlen($country) === 2 ? $country : null,
    ));

    return $eventData;
  }
}
