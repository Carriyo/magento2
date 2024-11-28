<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Controller\Adminhtml\Order;


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
     * Send constructor.
     * @param Action\Context $context
     * @param Helper $helper
     */
    public function __construct(
        Action\Context $context,
        Helper $helper
    )
    {
        $this->helper = $helper;
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
            $orderId = $this->getRequest()->getParam('order_id');
            if (!empty($orderId)) {
                $shipmentId = $this->helper->sendOrder($orderId);
                if (!empty($shipmentId)) {
                    $this->messageManager->addSuccessMessage(
                        'Successfully Created/Updated Shipment : ' . $shipmentId . ' in Carriyo.'
                    );
                }
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
