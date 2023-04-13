<?php

namespace Codilar\QrCode\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\Invoice\Item;
use Razorpay\Api\QrCode;
use Razorpay\Api\Api;
use Codilar\QrCode\Model\QrCodeFactory as Model;
use Codilar\QrCode\Model\ResourceModel\QrCodeFactory as ResourceModel;

/**
 * Class OrderManagement
 */
class OrderManagement
{
    /**
     * @var QrCode
     */
    public $qrCode;

    /**
     * @var Api
     */
    public $api;

    public $modelFactory;

    public $resourceModel;

    public function __construct(QrCode $qrCode, Model $modelFactory, ResourceModel $resourceModel)
    {
        $this->qrCode = $qrCode;
        $this->modelFactory = $modelFactory;
        $this->resourceModel = $resourceModel;
    }

    /**
     * @param OrderManagementInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterRegister(
        Item $subject, $result
    ) {
        $payment = (round($result->getInvoice()->getGrandTotal()))*100;
        $orderId = $result->getInvoice()->getOrder()->getIncrementId();
        $razorpay = new Api($keyId,$secret);
        if ($orderId) {
            $response = $razorpay->qrCode->create( array("type" => "upi_qr","name" => $orderId, "usage" => "single_use","fixed_amount" => true,"payment_amount" => $payment,"description" => "For Store 1","close_by" => 1681615838,"notes" => array("purpose" => "Test UPI QR code notes")));
        }
        $model = $this->modelFactory->create();
        $model->setData('qr_id',$response['id']);
        $model->setData('order_id',$orderId);
        $resourceModel = $this->resourceModel->create();
        $resourceModel->save($model);

        return $result;
    }
}
