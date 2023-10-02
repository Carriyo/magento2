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
 - Install the module composer by running `composer require carriyo/module-shipment:"^1.2.9"`. Replace version number if you want to use a previous version of the Carriyo module.
 - enable the module by running `php bin/magento module:enable Carriyo_Shipment`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

## Specifications

The following settings can be found in
`Stores -> Configuration -> Carriyo -> Settings`

- API Credentials
  - API Domain - (required)
  - Tenant ID - (required)
  - Merchant ID - (required)
  - API Key - (required)
  - Client ID - (required)
  - Client Secret - (required)
- Pickup Address
  - Location Code - (required)
- Carriyo Mappings
  - Shipping Methods - (required) A map between your sipping methods labels and Carriyo shipping type. Eg: `Flat Rate=STANDARD,Free Shipping=EXPRESS`
  - Allowed Order Statuses (COD): List of Magento statuses that are allowed to update Carriyo for COD orders. Eg: `pending,processing`
  - Allowed Order Statuses (Other Payment Types): List of Magento statuses that are allowed to update Carriyo for non COD orders. Eg: `processing`
  - Order Status Mapping: Map Carriyo statuses to update the Magento order status. Eg: `shipped=complete,cancelled=canceled`
  - Shipment Reference Prefix: Add an optional prefix to the Carriyo shipment reference. Use this when your Magento order numbers are not unique across merchants.

### Running in local environment
In order to run this environment please make sure you have `docker` and `docker-compose` installed.

 - Download this repo: [Clean docker Magento2](https://github.com/clean-docker/Magento2)
 - After that you need to download a [Magento2 project](https://magento.com/tech-resources/download)
 - Move your Magento project to be the `src` inside the docker repo.
 - Now you can just `docker-compose up -d` to spin up the project normally.
