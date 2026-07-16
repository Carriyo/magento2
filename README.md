# Mage2 Module Carriyo Shipment

    ``carriyo/module-shipment``

## Main Functionalities
This module integrates your ecommerce with Carriyo platform.

## Installation
\* = in production please use the `--keep-generated` option

### Type 1: Zip file

 - Unzip the zip file in `app/code/Carriyo`
 - Rename the unziped folder to `Shipment`
 - By this moment you should have the following folder tree
 ```
   app
     |-- code
           |-- Carriyo
	           |-- Shipment
 ```
 - Enable the module by running `php bin/magento module:enable Carriyo_Shipment`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Add the composer repository to the configuration by running `composer config repositories.repo.magento.com composer https://repo.magento.com/`
 - Install the module composer by running `composer require carriyo/module-shipment:"^2.0.0"`.
 - enable the module by running `php bin/magento module:enable Carriyo_Shipment`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

## Specifications

The following settings can be found in
`Stores -> Configuration -> Carriyo -> Settings`

- General
  - Enable toggle, debug logging, integration mode (`Orders` or `Shipments`)
  - Default location code and shipping method mapping
- Connection
  - API Base URL, Tenant ID, Merchant ID, API Key, Client ID, Client Secret
- Sync Triggers
  - Magento order statuses that send orders to Carriyo (COD vs other payment methods)
- Order Mode Settings (shown when integration mode is `Orders`)
  - Order status mapping, optional order reference prefix, default sales channel, delivery schedule field mappings
- Shipment Mode Settings (shown when integration mode is `Shipments`)
  - Shipment status mapping, auto-book toggle, optional shipment reference prefix

## Upgrading from 1.3.x to 2.0.0

Version 2.0.0 turns the connector from a shipment-only plugin into a dual-mode integration.

### What's new

- **Integration modes** — a new `Integration Mode` setting selects how Magento integrates with Carriyo:
  - `Shipments` (equivalent to the 1.3.x behaviour): Magento remains the fulfillment system and each order is sent to Carriyo as a shipment.
  - `Orders`: Magento sends orders to Carriyo and Carriyo manages fulfillment; shipments created in Carriyo are synced back to Magento, including Magento-native tracking links.
- **Order mode settings** — order status mapping, optional order reference prefix, default sales channel, and delivery schedule field mappings (map Magento order attributes to `delivery_schedule.scheduled_from`/`scheduled_to`).
- **Restructured admin configuration** — settings are grouped into General, Connection, Sync Triggers, and per-mode groups that only appear for the selected integration mode.
- **Item weights** are now included on synced line items.
- **Automatic sync dedupe** — outbound syncs are hashed so unchanged orders are not re-sent on every save.

### Behaviour changes

- Carriyo shipment webhooks with a status that is not in the Shipment Status Mapping are now acknowledged and ignored, instead of failing with HTTP 400 and being retried by Carriyo.
- Default status mappings are trimmed to the transitions that change the Magento order state; statuses not mapped are ignored. Existing saved mappings are not modified.
- Skipped status updates no longer add noisy comments to the order status history.

### Upgrade steps

1. Update the module (`composer require carriyo/module-shipment:"^2.0.0"` or replace `app/code/Carriyo/Shipment`).
2. Run `php bin/magento setup:upgrade` — a data migration renames the old sync-flow values to the new integration modes (`shipment_only` → `shipments`, `order_and_shipment` → `orders`) and defaults to `shipments` when unset, so 1.3.x installs keep their current behaviour.
3. Run `php bin/magento setup:di:compile` and `php bin/magento cache:flush`.
4. Review `Stores -> Configuration -> Carriyo -> Settings`: confirm the Integration Mode and the status mappings for your mode.

### Running in local environment
In order to run this environment please make sure you have `docker` and `docker-compose` installed.

 - Download this repo: [Clean docker Magento2](https://github.com/clean-docker/Magento2)
 - After that you need to download a [Magento2 project](https://magento.com/tech-resources/download)
 - Move your Magento project to be the `src` inside the docker repo.
 - Now you can just `docker-compose up -d` to spin up the project normally.
