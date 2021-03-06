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
 - Install the module composer by running `composer require carriyo/module-shipment:"^1.2.5"`
 - enable the module by running `php bin/magento module:enable Carriyo_Shipment`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

## Specifications

The following fields can be find in
`Stores -> Configuration -> Carriyo -> Settings`

 - API Credentials
	- Api Key - (required)
	- Tenant ID - (required) Have in mind that this field is case sensitive.
	- Client ID - (required)
	- Client Secret - (required)
	- Merchant (required) - (required)
 -  API Endpoints
	- Api URL - (required) The Carriyo API url
	- Api OAuth URL - (required) The Carriyo autentication url
 -  Pickup Address
	- Location Code - (required)
 -  Shipping Method Map
	- Shipping Methods - (required) A map between your sipping methods labels and Carriyo shipping type. Exp `Flat Rate=STANDARD,Free Shipping=EXPRESS`


### Running in local environment
In order to run this environment please make sure you have `docker` and `docker-compose` installed.

 - Download this repo: [Clean docker Magento2](https://github.com/clean-docker/Magento2)
 - After that you need to download a [Magento2 project](https://magento.com/tech-resources/download)
 - Move your Magento project to be the `src` inside the docker repo.
 - Now you can just `docker-compose up -d` to spin up the project normally.
