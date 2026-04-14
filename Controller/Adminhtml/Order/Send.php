<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Controller\Adminhtml\Order;


use Carriyo\Shipment\Model\Configuration;
use Carriyo\Shipment\Model\Helper;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Send
 *
 * @package Carryio\Shipping\Controller\Adminhtml\Order\Shipment
 */
class Send extends \Magento\Backend\App\Action
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * Send constructor.
     * @param Action\Context $context
     * @param Helper $helper
     */
    public function __construct(
        Action\Context $context,
        Helper $helper,
        Configuration $configuration
    )
    {
        $this->helper = $helper;
        $this->configuration = $configuration;
        parent::__construct($context);
    }

    /**
     * Send the order to Carriyo service.
     *
     * @return Redirect
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');

        try {
            if (!empty($orderId)) {
                $syncReference = $this->helper->sendOrder($orderId);
                $message = $this->configuration->isOrderAndShipmentFlow()
                    ? (
                        $syncReference
                            ? __('Successfully synced order to Carriyo. Order ID: %1', $syncReference)
                            : __('Order was not synced to Carriyo.')
                    )
                    : (
                        $syncReference
                            ? __('Successfully created/updated shipment in Carriyo. Shipment ID: %1', $syncReference)
                            : __('Shipment was not synced to Carriyo.')
                    );
                $this->messageManager->addSuccessMessage($message);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
