<?php
namespace Codilar\QrCode\Block;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\OrderFactory;
use Codilar\QrCode\Model\AwbNumber\AwbNumber;
use Magento\Sales\Model\Order\InvoiceFactory;

class Invoice extends Template
{
        protected $invoice;
        protected $order;
        protected $awbNumber;

        public function __construct(Template\Context $context, OrderFactory $order,InvoiceFactory $invoice,AwbNumber $awbNumber,array $data = [])
        {
            $this->invoice = $invoice;
            $this->order = $order;
            $this->awbNumber = $awbNumber;
            parent::__construct($context, $data);
        }
        
        public function getCoreTax($invoiceId){
            $coreTax = '';
            $invoiceDetails = $this->invoice->create()->loadByIncrementId($invoiceId);
            $orderTax = $invoiceDetails->getTaxAmount();
            $coreTax = round($orderTax/2,2);
            return $coreTax;
        }

        public function getCoreOrderTotal($invoiceId){
            $invoiceDetails = $this->invoice->create()->loadByIncrementId($invoiceId);
            $orderTotal = round($invoiceDetails->getGrandTotal());
            return $orderTotal;
        }

        public  function getEcomInvoiceDate($invoiceId){
            $invoiceDetails = $this->invoice->create()->loadByIncrementId($invoiceId);

            $orderId = $invoiceDetails->getOrder()->getIncrementId();

             $ecomData = $this->awbNumber->getAwbNumber($orderId);
             if(!empty($ecomData)) {
                 $invoiceDate = $ecomData[3];
                 return $invoiceDate;
        }else{
             return null;
             }
        }

}
