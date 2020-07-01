<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Controller\Adminhtml\Order\Shipment;


use Carriyo\Shipment\Core\Api\Client;
use Carriyo\Shipment\Model\Configuration;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;

/**
 * Class Send
 *
 * @package Carryio\Shipping\Controller\Adminhtml\Order\Shipment
 */
class Send extends \Magento\Backend\App\Action
{
    /**
     * @var ShipmentLoader
     */
    protected $shipmentLoader;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Client
     */
    private $carriyoClient;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * @param Action\Context $context
     * @param ShipmentLoader $shipmentLoader
     * @param Configuration $configuration
     * @param Client $carriyoClient
     * @param ShipmentRepository $shipmentRepository
     * @param TrackFactory $trackFactory
     */
    public function __construct(
        Action\Context $context,
        ShipmentLoader $shipmentLoader,
        Configuration $configuration,
        Client $carriyoClient,
        ShipmentRepository $shipmentRepository,
        TrackFactory $trackFactory
    ) {
        $this->shipmentLoader = $shipmentLoader;
        $this->configuration = $configuration;
        $this->shipmentLoader = $shipmentLoader;
        $this->carriyoClient = $carriyoClient;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;

        parent::__construct($context);
    }

    /**
     * Send the shipment to Carriyo service.
     *
     * @return Redirect
     */
    public function execute()
    {
        try {
            $this->shipmentLoader->setOrderId($this->getRequest()->getParam('order_id'));
            $this->shipmentLoader->setShipmentId($this->getRequest()->getParam('shipment_id'));
            $shipment = $this->shipmentLoader->load();

            if ($shipment) {
                $response = $this->carriyoClient->send($shipment->getOrder(), $shipment);
                if (!array_key_exists('errors', $response)) {

                    $track = $this->trackFactory->create();
                    $track->setCarrierCode('custom');
                    $track->setTitle('CARRIYO-' . $response['carrier_account']['carrier_account_name']);
                    $track->setTrackNumber($response['shipment_id']);

                    $shipment->addTrack($track);
                    $this->shipmentRepository->save($shipment);

                    $this->messageManager->addSuccessMessage(
                        'Track your shipment: ' . $response['shipment_id'] . ' on Carriyo dashboard.'
                    );
                }

                if (array_key_exists('errors', $response)) {
                    $this->messageManager->addWarningMessage($response['errors'][0]);
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/shipment/view', ['shipment_id' => $this->getRequest()->getParam('shipment_id')]);
    }
}
