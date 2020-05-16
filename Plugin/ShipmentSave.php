<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin;


use Carriyo\Shipment\Core\Api\Client;
use Carriyo\Shipment\Model\Configuration;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;

/**
 * Class ShipmentSave
 * @package Carriyo\Shipment\Plugin
 */
class ShipmentSave
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var ShipmentLoader
     */
    private $shipmentLoader;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Client
     */
    private $carriyoClient;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * ShipmentSave constructor.
     *
     * @param Configuration $configuration
     * @param Client $carriyoClient
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentLoader $shipmentLoader
     * @param RedirectFactory $resultRedirectFactory
     * @param Context $context
     * @param ShipmentRepository $shipmentRepository
     * @param TrackFactory $trackFactory
     */
    public function __construct(
        Configuration $configuration,
        Client $carriyoClient,
        OrderRepositoryInterface $orderRepository,
        ShipmentLoader $shipmentLoader,
        RedirectFactory $resultRedirectFactory,
        Context $context,
        ShipmentRepository $shipmentRepository,
        TrackFactory $trackFactory
    )
    {
        $this->configuration = $configuration;
        $this->shipmentLoader = $shipmentLoader;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->context = $context;
        $this->carriyoClient = $carriyoClient;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
    }

    /**
     * @param Save $subject
     * @param $result
     * @return mixed
     */
    public function afterExecute(
        Save $subject,
        $result
    )
    {
        if (!$this->configuration->isActive()) {
            return $result;
        }

        try {
            $messageManager = $this->context->getMessageManager();
            $order = $this->orderRepository->get($subject->getRequest()->getParam('order_id'));
            /** @var Collection $collection */
            $collection = $order->getShipmentsCollection();
            $shipment = $this->shipmentRepository
                ->get($collection->getLastItem()->getData('entity_id'));

            $response = $this->carriyoClient->send($order, $shipment);

            if (!array_key_exists('errors', $response)) {

                $track = $this->trackFactory->create();
                $track->setCarrierCode('custom');
                $track->setTitle('CARRIYO-' . $response['carrier_account']['carrier_account_name']);
                $track->setTrackNumber($response['shipment_id']);

                $shipment->addTrack($track);
                $this->shipmentRepository->save($shipment);

                $messageManager->addSuccessMessage(
                    'Track your shipment: ' . $response['shipment_id'] . ' on Carriyo dashboard.'
                );
            }

            if (array_key_exists('errors', $response)) {
                $messageManager->addWarningMessage($response['errors'][0]);
            }

        } catch (\Exception $e) {
        }

        return $result;
    }
}
