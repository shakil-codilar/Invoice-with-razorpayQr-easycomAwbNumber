<?php
namespace Codilar\QrCode\Model\Order\Pdf;

use Magento\Sales\Model\Order\Pdf\Config;
use Razorpay\Api\Api;
use Laminas\Barcode;

use PHPQRCode\QRcode;
use Codilar\QrCode\Model\AwbNumber\AwbNumber;
use Magento\Framework\UrlInterface;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\EncoderInterface;
use Codilar\QrCode\Model\TinNumber\TaxIdentificationNumber;
use Codilar\QrCode\Model\ResourceModel\QrCode\CollectionFactory as Collection;

class Invoice extends \Magento\Sales\Model\Order\Pdf\Invoice
{
    protected $urlBuilder;
    protected $awbNumber;

    protected $tinNumber;

    protected $collection;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    public function __construct(
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem $filesystem,
        Config $pdfConfig,
        \Magento\Sales\Model\Order\Pdf\Total\Factory $pdfTotalFactory,
        \Magento\Sales\Model\Order\Pdf\ItemsFactory $pdfItemsFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        AwbNumber $awbNumber,
        UrlInterface $urlBuilder,
        EncoderInterface $urlEncoder,
        DecoderInterface $urlDecoder,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        TaxIdentificationNumber $tinNumber,
        Collection $collection,
        array $data = []
    )
    {
        parent::__construct($paymentData, $string, $scopeConfig, $filesystem, $pdfConfig, $pdfTotalFactory, $pdfItemsFactory, $localeDate, $inlineTranslation, $addressRenderer, $storeManager, $appEmulation, $data);
        $this->_localeResolver = $localeResolver;
        $this->awbNumber = $awbNumber;
        $this->urlBuilder = $urlBuilder;
        $this->urlEncoder = $urlEncoder;
        $this->urlDecoder = $urlDecoder;
        $this->encryptor = $encryptor;
        $this->tinNumber = $tinNumber;
        $this->collection = $collection;
    }

    public function getPdf( $invoices = [] )
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('invoice');

        $pdf = new \Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new \Zend_Pdf_Style();
        $this->_setFontBold($style, 10);
        $currentHeight = $this->y;
        /* Signature height start */
        $itemCoverendY = 0;

        foreach ($invoices as $invoice)
        {
            if ($invoice->getStoreId())
            {
                $this->_localeResolver->emulate($invoice->getStoreId());
                $this->_storeManager->setCurrentStore($invoice->getStoreId());
            }
            $page = $this->newPage();
            $order = $invoice->getOrder();

            // Initialize variables

            /*Add CustomText */
            $this->insertText($page,$invoice->getStore());

            /* Add image */
            $this->insertLogo($page, $invoice->getStore());

            /* Add Order Qr Code */
            $invoiceId =$invoice->getIncrementId();
            $this->insertOrderQr($page,$order,$invoiceId);

            /* Add head */
            $this->insertOrders(
                $page,
                $order,
                $invoiceId,
                $invoice,
                $this->_scopeConfig->isSetFlag(
                    self::XML_PATH_SALES_PDF_INVOICE_PUT_ORDER_ID,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $order->getStoreId()
                )
            );

            /* CGST TOP */
            $cgstTop = $this->y-50;
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontBold($page, 8);

            $page->drawLine(404, $cgstTop+15, 446, $cgstTop+15);
            $page->drawLine(448, $cgstTop+15, 506, $cgstTop+15);
            $page->drawLine(508, $cgstTop+15, 564, $cgstTop+15);

            $page->drawText(__('Tax Breakup'), 454, $cgstTop+20, 'UTF-8');
            $page->drawText(__('CGST'), 460, $cgstTop, 'UTF-8');
            $page->drawText(__('SGST'), 520, $cgstTop, 'UTF-8');

            /* Items cover top*/
            $page->drawLine(25, $cgstTop+35, 570, $cgstTop+35);

            $this->_drawHeader($page,$order);


            /* Add body */
            $count =1;


            $itemPrice = 0;
            $taxAmount = 0;
            $itemHeight =0;
            $startItem =$cgstTop+15-65;

            /*CGST and Item cover Side line draw */
            $page->drawLine(567, $cgstTop+15, 567, $cgstTop+15-63);
            $page->drawLine(570, $cgstTop+15+20, 570, $cgstTop+15-63);
            $page->drawLine(570, $cgstTop+15, 570, $cgstTop+15-65);

            $page->drawLine(25, $cgstTop+15+20, 25, $cgstTop+15-63);
            $page->drawLine(25, $cgstTop+15, 25, $cgstTop+15-65);

            foreach ($invoice->getAllItems() as $item)
            {
                if ($item->getOrderItem()->getParentItem())
                {
                    continue;
                }

                /* Draw item */
                $this->_drawItems($item, $page, $order, $count);
                $itemPrice = $itemPrice+ $item->getPrice()*$item->getQty();
                /* for serial number */
                $count =$count +1;
                /* for tax amount */
                $taxAmount = $taxAmount + round($item->getTaxAmount()/2,4);

                /* Items Height calculation */
                $itemName = $this->_formatAddress($item->getName());
                $itemHeight = $this->_calcItemHeight($itemName);
                /* Item Side Line draw */
                if($startItem - $itemHeight >45) {
                    $page->drawLine(567, $startItem, 567, $startItem - $itemHeight);
                    /* total cover item portion draw left and right  */
                    $page->drawLine(570, $startItem, 570, $startItem - $itemHeight);
                    $page->drawLine(25, $startItem, 25, $startItem - $itemHeight);
                    $temp = $startItem;
                    $startItem = $startItem - $itemHeight;
                    $startItem - $itemHeight = $temp;
                }

                $page = end($pdf->pages);
            }

            /* insert totals */
            $totalTop = $this->y-10;

            /* Item Side Line draw  for another pdf page */
            if($startItem - $itemHeight <15 && $count >11) {  //if($startItem - $itemHeight <15 && $count >11)
                $page->drawLine(567, 750, 567, $totalTop);
                /* total cover item portion draw left and right */
                $page->drawLine(570, 750, 570, $totalTop);
                $page->drawLine(25, 750, 25, $totalTop);
            }

            if ($totalTop < 110)  {
                $page = $this->newPage();
                $totalTop = 750;
            }

            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontBold($page, 8);

            /* Total DOWN */
            $page->drawLine(446, $totalTop-5, 404, $totalTop-5);
            $page->drawLine(506, $totalTop-5, 448, $totalTop-5);
            $page->drawLine(564, $totalTop-5, 508, $totalTop-5);
            /* Total side line & Item cover(total side line portion left and right) Side draw */
            $page->drawLine(567, $totalTop-5+30, 567, $totalTop-5);
            $page->drawLine(570, $totalTop-5+35, 570, $totalTop-7);
            $page->drawLine(25, $totalTop-5+35, 25, $totalTop-7);

            $page->drawText(__('Total'), 255, $totalTop, 'UTF-8');
            $page->drawText(__('Total Item Taxable Value'), 255, $totalTop-15, 'UTF-8');
            $page->drawText(__('Total Item Tax'), 255, $totalTop-30, 'UTF-8');

            if($invoice->getSubtotal()+$invoice->getDiscountAmount()<300 && $order->getPayment()->getMethod()=="cashondelivery" ) {
                $page->drawText(__('Shipping Charges(Tax Inclusive) '), 255, $totalTop - 45, 'UTF-8');
                $page->drawText(__('COD Charges(Tax Inclusive) '), 255, $totalTop - 60, 'UTF-8');
                $page->drawText(__('Invoice Value '), 255, $totalTop - 75, 'UTF-8');
                $page->drawText(__('Final Invoice Amount'), 255, $totalTop - 90, 'UTF-8');
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()<300 && $order->getPayment()->getMethod()!=="cashondelivery" ){
                $page->drawText(__('Shipping Charges(Tax Inclusive) '), 255, $totalTop - 45, 'UTF-8');
                $page->drawText(__('Invoice Value '), 255, $totalTop - 60, 'UTF-8');
                $page->drawText(__('Final Invoice Amount'), 255, $totalTop - 75, 'UTF-8');
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()>300 && $order->getPayment()->getMethod()=="cashondelivery" ){
                $page->drawText(__('COD Charges(Tax Inclusive) '), 255, $totalTop - 45, 'UTF-8');
                $page->drawText(__('Invoice Value '), 255, $totalTop - 60, 'UTF-8');
                $page->drawText(__('Final Invoice Amount'), 255, $totalTop - 75, 'UTF-8');
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()>300 && $order->getPayment()->getMethod()!=="cashondelivery" ){
                $page->drawText(__('Invoice Value '), 255, $totalTop - 45, 'UTF-8');
                $page->drawText(__('Final Invoice Amount'), 255, $totalTop - 60, 'UTF-8');
            }
            /* Insert Totals Data */

            $page->drawText(round($invoice->getSubtotal()+$invoice->getDiscountAmount(),3), 415, $totalTop, 'UTF-8');
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontBold($page, 7.5);
            if($taxAmount==0){
                $page->drawText($taxAmount, 495, $totalTop, 'UTF-8',);
                $page->drawText($taxAmount, 550, $totalTop, 'UTF-8');
            }else {
                $page->drawText($taxAmount, 479, $totalTop, 'UTF-8',);
                $page->drawText($taxAmount, 535, $totalTop, 'UTF-8');
            }
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontBold($page, 8);
            $page->drawText(round($invoice->getSubtotal()+$invoice->getDiscountAmount(),3), 415, $totalTop-15, 'UTF-8');
            $page->drawText(round($invoice->getTaxAmount(),2), 415, $totalTop-30, 'UTF-8');
            /* Items cover Side down left and right*/
            $page->drawLine(570, $totalTop, 570, $totalTop-45);
            $page->drawLine(25, $totalTop, 25, $totalTop-45);


            if($invoice->getSubtotal()+$invoice->getDiscountAmount()<300 && $order->getPayment()->getMethod()=="cashondelivery" ) {
                $page->drawText(30, 415, $totalTop-45, 'UTF-8');
                $page->drawText(20, 415, $totalTop-60, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal(),2), 415, $totalTop-75, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal()), 415, $totalTop-90, 'UTF-8');
                /* Items cover Side down left and right*/
                $page->drawLine(570, $totalTop-45, 570, $totalTop-95);
                $page->drawLine(25, $totalTop-45, 25, $totalTop-95);

                $page->drawLine(570, $totalTop-95, 25, $totalTop-95);

                $itemCoverendY = $totalTop-95;
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()<300 && $order->getPayment()->getMethod()!=="cashondelivery" ){
                $page->drawText(30, 415, $totalTop-45, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal(),2), 415, $totalTop-60, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal()), 415, $totalTop-75, 'UTF-8');
                /* Items cover Side down left and right */
                $page->drawLine(570, $totalTop-45, 570, $totalTop-80);
                $page->drawLine(25, $totalTop-45, 25, $totalTop-80);

                $page->drawLine(570, $totalTop-80, 25, $totalTop-80);
                $itemCoverendY = $totalTop-80;
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()>300 && $order->getPayment()->getMethod()=="cashondelivery" ){
                $page->drawText(20, 415, $totalTop-45, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal(),2), 415, $totalTop-60, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal()), 415, $totalTop-75, 'UTF-8');
                /* Items cover Side down left and right*/
                $page->drawLine(570, $totalTop-35, 570, $totalTop-80);
                $page->drawLine(25, $totalTop-35, 25, $totalTop-80);
                $page->drawLine(570, $totalTop-80, 25, $totalTop-80);
                $itemCoverendY = $totalTop-80;
            }
            elseif ($invoice->getSubtotal()+$invoice->getDiscountAmount()>300 && $order->getPayment()->getMethod()!=="cashondelivery" ){
                $page->drawText(round($invoice->getGrandTotal(),2), 415, $totalTop-45, 'UTF-8');
                $page->drawText(round($invoice->getGrandTotal()), 415, $totalTop-60, 'UTF-8');
                /* Items cover Side down left right*/
                $page->drawLine(570, $totalTop-45, 570, $totalTop-65);
                $page->drawLine(25, $totalTop-45, 25, $totalTop-65);

                $page->drawLine(570, $totalTop-65, 25, $totalTop-65);
                $itemCoverendY = $totalTop-65;
            }


            $this->y = $totalTop -100;

            /* Insert totals end */

            if ($invoice->getStoreId())
            {
                $this->_localeResolver->revert();
            }
            /* Last Section Before Qr */

            $signatureStartY = $itemCoverendY -20;
            if ($signatureStartY < 120)  {
                $page = $this->newPage();
                $signatureStartY = 750;
            }
            $this->lastSection($page,$signatureStartY,$order, $currentHeight,$invoice);
        }

        $this->_afterGetPdf();
        return $pdf;
    }

    protected function insertOrders(&$page, $obj,$invoiceId, $invoice, $putOrderId = true)
    {
        if ($obj instanceof \Magento\Sales\Model\Order) {
            $shipment = null;
            $order = $obj;
        } elseif ($obj instanceof \Magento\Sales\Model\Order\Shipment) {
            $shipment = $obj;
            $order = $shipment->getOrder();
        }

        $this->y = $this->y ? $this->y : 815;
        $top = $this->y;


        //Rectangle Grids
        $page->setFillColor(new \Zend_Pdf_Color_Rgb(1, 1, 1));
        $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $page->setLineWidth(0.5);
        $upBoxTop = $top + 30;
        $upBoxBottom = $top - 75;
        $lowBoxTop = $upBoxBottom;
        $lowBoxBottom = $top - 205;

        $page->drawRectangle(25, $upBoxTop, 275, $upBoxBottom);
        $page->drawRectangle(275, $upBoxTop, 423, $upBoxBottom);
        $page->drawRectangle(423, $upBoxTop, 570, $upBoxBottom);

        //Hieght calculation for Rectangle moving with text length
        $billingAddress = $this->_formatAddress($this->addressRenderer->format($order->getBillingAddress(), 'pdf'));
        /* Shipping Address and Method */
        if (!$order->getIsVirtual()) {
            /* Shipping Address */
            $shippingAddress = $this->_formatAddress(
                $this->addressRenderer->format($order->getShippingAddress(), 'pdf')
            );
            $shippingMethod = $order->getShippingDescription();
        }
        $addressesHeight = $this->_calcAddressHeight($billingAddress);
        $addressesH = $this->_calcAddressHeight($shippingAddress);



        if (isset($shippingAddress)) {
            $addressesHeight = max($addressesHeight, $addressesH);
        }

        $itemGridStartY = $lowBoxTop-48-$addressesHeight;

        $page->drawRectangle(25, $lowBoxTop, 155, $lowBoxTop-48-$addressesHeight);
        $page->drawRectangle(155, $lowBoxTop, 285, $lowBoxTop-48-$addressesHeight);
        $page->drawRectangle(285, $lowBoxTop, 423, $lowBoxTop-48-$addressesHeight);
        $page->drawRectangle(423, $lowBoxTop, 570, $lowBoxTop-48-$addressesHeight);

        $this->setDocHeaderCoordinates([25, $top, 570, $top - 55]);

        //First Rectangle Data
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('SELLER'), 30, $upBoxTop - 14, 'UTF-8');
        $page->drawText(__('RSH Global Private Limited'), 30, $upBoxTop - 27, 'UTF-8');
        $page->drawText(__('Regd Office:'), 30, $upBoxTop - 42, 'UTF-8');
        $page->drawText(__('Phn No:'), 30, $upBoxTop - 65, 'UTF-8');
        $page->drawText(__('Email:'), 30, $upBoxTop - 76, 'UTF-8');
        $page->drawText(__('CIN:'), 30, $upBoxTop - 86, 'UTF-8');
        $page->drawText(__('PAN:'), 30, $upBoxTop - 96, 'UTF-8');
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $page->drawText(__(' Unit-2C, 2nd Floor, 119 Park Street, '), 80, $upBoxTop - 42, 'UTF-8');
        $page->drawText(__('White House, Kolkata - 700016'), 30, $upBoxTop - 52, 'UTF-8');
        $page->drawText(__('033-4016 7300'), 64, $upBoxTop - 65, 'UTF-8');
        $page->drawText(__('customercare@joycosmetics.com'), 58, $upBoxTop - 76, 'UTF-8');
        $page->drawText(__('U2426WB2005PTC103808'), 47, $upBoxTop - 86, 'UTF-8');
        $page->drawText(__('AADCR0379P'), 52, $upBoxTop - 96, 'UTF-8');

        //Second Rectangle Data
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('SHIPPED FROM'), 280, $upBoxTop - 14, 'UTF-8');
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $page->drawText(__('RSH Global Private Limited'), 280, $upBoxTop - 27, 'UTF-8');
        $page->drawText(__('Unit-2C, 2nd Floor, 119 Park Street, '), 280, $upBoxTop - 42, 'UTF-8');
        $page->drawText(__('White House,'), 280, $upBoxTop - 52, 'UTF-8');
        $page->drawText(__('Kolkata - 700016'), 280, $upBoxTop - 62, 'UTF-8');
        $page->drawText(__('State Code : 19'), 280, $upBoxTop - 73, 'UTF-8');
        $page->drawText(__('GSTIN : 19AADCR0379P2ZP'), 280, $upBoxTop - 84, 'UTF-8');

        //Fourth Rectangle Title & Data
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('BILLED TO'), 30, $lowBoxTop - 14, 'UTF-8');
        $billingAddress = $this->_formatAddress($this->addressRenderer->format($order->getBillingAddress(), 'pdf'));
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $this->y = $lowBoxTop - 27;
        foreach ($billingAddress as $value) {
            if ($value !== '') {
                $text = [];
                foreach ($this->string->split($value, 25, true, true) as $_value) {
                    $text[] = $_value;
                }
                foreach ($text as $part) {
                    $page->drawText(strip_tags(ltrim($part)), 30, $this->y, 'UTF-8');
                    $this->y -= 10;
                }
            }
        }

        $stateCodeOfBilling = $order->getBillingAddress()->getRegionCode();
        $stateOfBilling = $order->getBillingAddress()->getRegion();
        $tinOfBilling = $this->tinNumber->getTinNumber($stateCodeOfBilling);

        $beforeSC = $this->y;
        $page->drawText(__('State Code:'), 30, $beforeSC-1, 'UTF-8');
        $page->drawText($tinOfBilling, 75, $beforeSC-1, 'UTF-8');
        $this->_setFontRegular($page, 7.8);
        $page->drawText(__('Place Of Supply:'), 30, $beforeSC - 12, 'UTF-8');
        $page->drawText($stateOfBilling, 88, $beforeSC-12, 'UTF-8');

        //Fifth Rectangle Title & Data
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        if (!$order->getIsVirtual()) {
            /* Shipping Address */
            $shippingAddress = $this->_formatAddress(
                $this->addressRenderer->format($order->getShippingAddress(), 'pdf')
            );
            $shippingMethod = $order->getShippingDescription();
        }

        $page->drawText(__('SHIPPED TO'), 160, $lowBoxTop - 14, 'UTF-8');
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $this->y = $lowBoxTop - 30;

        if (!$order->getIsVirtual()) {
            $this->y = $lowBoxTop - 27;
            foreach ($shippingAddress as $value) {
                if ($value !== '') {
                    $text = [];
                    foreach ($this->string->split($value, 25, true, true) as $_value) {
                        $text[] = $_value;
                    }
                    foreach ($text as $part) {
                        $page->drawText(strip_tags(ltrim($part)), 160, $this->y, 'UTF-8');
                        $this->y -= 10;
                    }
                }
            }
        }
        $stateCodeOfShipping = $order->getShippingAddress()->getRegionCode();
        $stateOfShipping = $order->getShippingAddress()->getRegion();
        $tinOfShipping = $this->tinNumber->getTinNumber($stateCodeOfShipping);
        $beforeSC = $this->y;
        $page->drawText(__('State Code:'), 160, $beforeSC-1, 'UTF-8');
        $page->drawText($tinOfShipping, 205, $beforeSC-1, 'UTF-8');
        $this->_setFontRegular($page, 7.8);
        $page->drawText(__('Place Of Supply:'), 160, $beforeSC - 12, 'UTF-8');
        $page->drawText($stateOfShipping, 218, $beforeSC-12, 'UTF-8');

        //6th Rectangle Title & Data
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('Portal - '), 295, $lowBoxTop - 28, 'UTF-8');
        $page->drawText(__('Order No.'), 295, $lowBoxTop - 38, 'UTF-8');

        //Barcode for order

        $this->orderBarcode($page,$order,$lowBoxTop);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 9);
        $page->drawText(__('Magento2.0'), 330, $lowBoxTop - 28, 'UTF-8');

        //Seventh Rectangle data

        /* get ecom data*/

        $order_id = $order->getIncrementId();
        $ecomData = $this->awbNumber->getAwbNumber($order_id);
        $awbNumber = '';
        $carrier = '';
        $orderDate ='';
        $invoiceDate = '';
        if(!empty($ecomData)) {
            $awbNumber = $ecomData[0];
            $carrier = $ecomData[1];
            $orderDate = $ecomData[2];
            $invoiceDate = $ecomData[3];
        }

        //Barcode for Invoice
        $this->invoiceBarcode($page,$order,$lowBoxTop,$orderDate,$invoiceDate,$invoiceId);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('Invoice Number'), 432, $lowBoxTop - 20, 'UTF-8');

        //Barcode for awb number

        $this->awbBarcode($page,$order,$awbNumber,$upBoxTop,$carrier,$invoice);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText(__('Awb Number'), 432, $upBoxTop - 10, 'UTF-8');

        //Product Table will be moved under this boundary
        $this->y = $itemGridStartY;
    }

    /* Barcode for awbNumber  */
    protected function awbBarcode($page, $order,$awbNumber,$upBoxTop,$carrier,$invoice){
        $text = $order->getIncrementId();
        $order_total = round($order->getGrandTotal());
        $invoicedTotal = round($invoice->getGrandTotal());
        $config = new \Zend_Config([
            'barcode' => 'code39',
            'barcodeParams' => [
                'text' => $awbNumber,
                'drawText' => false
            ],
            'renderer' => 'image',
            'rendererParams' => ['imageType' => 'png']
        ]);
        $barcodeResource = Barcode\Barcode::factory($config)->draw();
        ob_start();
        imagepng($barcodeResource);
        $barcodeImage = ob_get_clean();
        $image = new \Zend_Pdf_Resource_Image_Png('data:image/png;base64,' . base64_encode($barcodeImage));
        if ($image) {
            $page->drawImage($image,425,$upBoxTop-45,567,$upBoxTop-15);
        }
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText('*'.$awbNumber.'*', 466, $upBoxTop - 55, 'UTF-8');
        $page->drawText('Payment Mode:', 430, $upBoxTop - 67, 'UTF-8');
        if($order->getPayment()->getMethod()=='cashondelivery'){
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
            $this->_setFontBold($page, 8);
            $page->drawText('Collectable Amount:', 430, $upBoxTop - 80, 'UTF-8');
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontRegular($page, 8);
            $page->drawText('COD', 495, $upBoxTop - 67, 'UTF-8');
            $page->drawText($invoicedTotal, 514, $upBoxTop - 80, 'UTF-8');
        }else{
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
            $this->_setFontBold($page, 8);
            $page->drawText('Collected Amount:', 430, $upBoxTop - 80, 'UTF-8');
            $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
            $this->_setFontRegular($page, 8);
            $page->drawText('RAZORPAY', 495, $upBoxTop - 67, 'UTF-8');
            $page->drawText($invoicedTotal, 508, $upBoxTop - 80, 'UTF-8');
        }
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText('Carrier:', 430, $upBoxTop - 93, 'UTF-8');
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $page->drawText($carrier, 465, $upBoxTop - 93, 'UTF-8');
    }

    /* Barcode for Invoice Number */
    protected function invoiceBarcode($page, $order,$lowBoxTop,$orderDate,$invoiceDate,$invoiceId){

        $text = 'IN/WB/'. substr($invoiceId,4);
        $config = new \Zend_Config([
            'barcode' => 'code39',
            'barcodeParams' => [
                'text' => $text,
                'drawText' => false
            ],
            'renderer' => 'image',
            'rendererParams' => ['imageType' => 'png']
        ]);
        $barcodeResource = Barcode\Barcode::factory($config)->draw();
        ob_start();
        imagepng($barcodeResource);
        $barcodeImage = ob_get_clean();
        $image = new \Zend_Pdf_Resource_Image_Png('data:image/png;base64,' . base64_encode($barcodeImage));
        if ($image) {
            $page->drawImage($image,425,$lowBoxTop-60,567,$lowBoxTop-30);
        }
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 8);
        $page->drawText('*'.$text.'*', 466, $lowBoxTop - 70, 'UTF-8');
        $page->drawText(__('Invoice Date :'), 432, $lowBoxTop - 82, 'UTF-8');
        $page->drawText(__('Order Date :'), 432, $lowBoxTop - 94, 'UTF-8');
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontRegular($page, 8);
        $page->drawText($invoiceDate, 490, $lowBoxTop - 82, 'UTF-8');
        $page->drawText($orderDate, 485, $lowBoxTop - 94, 'UTF-8');
    }

    /* Barcode for Order Number */
    protected function orderBarcode($page, $order,$lowBoxTop){
        $text = $order->getIncrementId();
        $config = new \Zend_Config([
            'barcode' => 'code39',
            'barcodeParams' => [
                'text' => $text,
                'drawText' => false
            ],
            'renderer' => 'image',
            'rendererParams' => ['imageType' => 'png']
        ]);
        $barcodeResource = Barcode\Barcode::factory($config)->draw();
        ob_start();
        imagepng($barcodeResource);
        $barcodeImage = ob_get_clean();
        $image = new \Zend_Pdf_Resource_Image_Png('data:image/png;base64,' . base64_encode($barcodeImage));
        if ($image) {
            $page->drawImage($image,288,$lowBoxTop-80,420,$lowBoxTop-50);
        }
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.1));
        $this->_setFontBold($page, 8.5);
        $page->drawText('*'.$text.'*', 327, $lowBoxTop - 90, 'UTF-8');
    }


    public function insertQrCode(&$page,$obj,$currentHeight,$signatureStartY){
        if ($obj instanceof \Magento\Sales\Model\Order)
        {
            $shipment = null;
            $order = $obj;
        }
        elseif ($obj instanceof \Magento\Sales\Model\Order\Shipment)
        {
            $shipment = $obj;
            $order = $shipment->getOrder();
        }
        $this->y = $this->y ? $this->y : 815;
        $top = $signatureStartY+5;
        /* Start Qr Code */

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        foreach ($order->getInvoiceCollection() as $invoice)
        {
            $invoice_id = $invoice->getIncrementId();
        }
        $fileDriver = $objectManager->create('\Magento\Framework\Filesystem\Driver\File');
        $razorpay = new Api($keyId,$secretId);

        $orderRealId =$order->getRealOrderId();
        $collection =$this->collection->create();

        foreach ($collection->getData() as $key=>$data){
            if ($data['order_id']== $orderRealId){
                $qrInfo = $razorpay->qrCode->fetch($data['qr_id']);

                $imageLink = $qrInfo['image_url'];
                $fileSystem = $objectManager->create('\Magento\Framework\Filesystem');
                $tempDir = $fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::PUB)->getAbsolutePath('images/');
                $tempDir1 = $fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::PUB)->getAbsolutePath('qrcode/');

                $fileName ='razorpayImg.png';
                $fileName1 ='razorpayQrCode.png';
                $pngAbsoluteFilePath = $tempDir.$fileName;
                $pngAbsoluteFilePath1 = $tempDir1.$fileName1;

                $url = $imageLink;
                $img ='images/razorpayImg.png';

                file_put_contents($img, file_get_contents($url));

                $img1 = imagecreatefrompng($pngAbsoluteFilePath);
                $img2=imagecrop($img1, ['x' => 128, 'y' => 650, 'width' => 415, 'height' => 400]);
                if($img2 !== FALSE) {
                    imagepng($img2, $pngAbsoluteFilePath1);
                    imagedestroy($img2);
                }
                imagedestroy($img1);


                $image = \Zend_Pdf_Image::imageWithPath($pngAbsoluteFilePath1);
                /*width,height,*/
                $page->setFillColor(new \Zend_Pdf_Color_Rgb(1, 1, 1));
                $page->setLineColor(new \Zend_Pdf_Color_GrayScale(0.2));
                $page->setLineWidth(1);

                $page->drawRectangle(576-80, $top-68, 576-5, $top+3);
                $page->drawImage($image,576-78,$top - 65,576-7,$top+0);

                $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
                $this->_setFontBold($page, 5);
                $page->drawText(__('Scan QR Code for making Bill'), 576-80, $top-75, 'UTF-8');
                $page->drawText(__('Payment through Internet'), 576-78, $top-81, 'UTF-8');

                //logo inside qr

                $logoDir = $fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::PUB)->getAbsolutePath('rshqrlogo/');

                $logoName ='insideqr.jpg';
                $logoAbsoluteFilePath = $logoDir.$logoName;

                $logoUrl = 'https://rshglobal.com/wp-content/uploads/2018/11/logo.jpg';
                $logo ='rshqrlogo/insideqr.jpg';
                file_put_contents($logo, file_get_contents($logoUrl));

                $logoImage = \Zend_Pdf_Image::imageWithPath($logoAbsoluteFilePath);

                $page->drawImage($logoImage,576-55,$top - 38,576-30,$top-28);


            }
        }
        /* End Qr Code */
    }


    protected function insertOrderQr(&$page,$obj,$invoiceId){
        if ($obj instanceof \Magento\Sales\Model\Order)
        {
            $shipment = null;
            $order = $obj;
        }
        elseif ($obj instanceof \Magento\Sales\Model\Order\Shipment)
        {
            $shipment = $obj;
            $order = $shipment->getOrder();
        }
        $this->y = $this->y ? $this->y : 815;
        $top = $this->y;

        /* Start Qr Code */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $invoiceId = $this->encryptor->encrypt($invoiceId);
        $encodeId = $this->urlEncoder->encode($invoiceId);

        $landingUrl = $this->urlBuilder->getBaseUrl().'qrcode/invoice/index/id/'.$encodeId ;
        $fileSystem = $objectManager->create('\Magento\Framework\Filesystem');
        $tempDir = $fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::PUB)->getAbsolutePath('orderqr/');
        $codeContents=$landingUrl;
        $fileName = 'orderQR.png';

        $pngAbsoluteFilePath = $tempDir.$fileName;

        $fileDriver = $objectManager->create('\Magento\Framework\Filesystem\Driver\File');


        QRcode::png($codeContents, $pngAbsoluteFilePath, 'L', 4, 2);


        $image = \Zend_Pdf_Image::imageWithPath($pngAbsoluteFilePath);

        /*width,height,*/
        $page->drawImage($image,505,715,570,780);

        /* End Qr Code */
    }

    protected function _drawHeader(\Zend_Pdf_Page $page)
    {
        /* Add table head */
        $this->y -= 70;
        //columns headers
        $lines[0][] = ['text' =>$this->string->split(__('Sr No.'), 5, true, true), 'feed' => 35 , 'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Description Of Goods'), 15, true, true), 'feed' => 55 , 'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' => __('SKU'), 'feed' => 180, 'align' => 'right','font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('HSN Code'), 5, true, true), 'feed' => 230,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' => __('Price'), 'feed' => 305, 'align' => 'right','font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' => __('Qty'), 'feed' => 340, 'align' => 'right','font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Item Discount'), 10, true, true), 'feed' => 352,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Taxable Value'), 8, true, true), 'feed' => 404,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Rate'), 5, true, true), 'feed' => 448,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Tax Amt.'), 5, true, true), 'feed' => 480,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Rate'), 5, true, true), 'feed' => 508,'font' =>'bold', 'font_size'=>9];

        $lines[0][] = ['text' =>$this->string->split(__('Tax Amt.'), 5, true, true), 'feed' => 540, 'align' => 'left','font' =>'bold', 'font_size'=>9];

//        $lines[0][] = ['text' => __('Subtotal'), 'feed' => 565, 'align' => 'right','font' =>'bold', 'font_size'=>9];

        $lineBlock = ['lines' => $lines, 'height' => 10];

        $this->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->y -= 20;
    }


    protected function _drawItems(
        \Magento\Framework\DataObject $item,
        \Zend_Pdf_Page $page,
        \Magento\Sales\Model\Order $order,
                                      $count
    ) {
        $type = $item->getOrderItem()->getProductType();
        $renderer = $this->_getRenderer($type);
        $renderer->setOrder($order);
        $renderer->setItem($item);
        $renderer->setPdf($this);
        $renderer->setPage($page);
        $renderer->setRenderedModel($this);
        $renderer->draws($count);


        return $renderer->getPage();
    }

    protected function _calcAddressHeight($address)
    {
        $y = 0;
        foreach ($address as $value) {
            if ($value !== '') {
                $text = [];
                foreach ($this->string->split($value, 25, true, true) as $_value) {
                    $text[] = $_value;
                }
                foreach ($text as $part) {
                    $y += 10;
                }
            }
        }
        return $y;
    }
    protected function _calcItemHeight($address)
    {
        $y = 0;
        foreach ($address as $value) {
            if ($value !== '') {
                $text = [];
                foreach ($this->string->split($value, 20, true, true) as $_value) {
                    $text[] = $_value;
                }
                foreach ($text as $part) {
                    $y += 10;
                }
            }
        }
        return $y;
    }

    protected function insertLogo(&$page, $store = null)
    {
        $this->y = $this->y ? $this->y : 815;
        $image = $this->_scopeConfig->getValue(
            'sales/identity/logo',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
        if ($image) {
            $imagePath = '/sales/store/logo/' . $image;

            if ($this->_mediaDirectory->isFile($imagePath)) {
                $image = \Zend_Pdf_Image::imageWithPath($this->_mediaDirectory->getAbsolutePath($imagePath));
                $top = 770;
                //top border of the page
                $widthLimit = 70;
                //half of the page width
                $heightLimit = 70;
                //assuming the image is not a "skyscraper"
                $width = $image->getPixelWidth();
                $height = $image->getPixelHeight();

                //preserving aspect ratio (proportions)
                $ratio = $width / $height;
                if ($ratio > 1 && $width > $widthLimit) {
                    $width = $widthLimit;
                    $height = $width / $ratio;
                } elseif ($ratio < 1 && $height > $heightLimit) {
                    $height = $heightLimit;
                    $width = $height * $ratio;
                } elseif ($ratio == 1 && $height > $heightLimit) {
                    $height = $heightLimit;
                    $width = $widthLimit;
                }

                $y1 = $top - $height;
                $y2 = $top;
                $x1 = 25;
                $x2 = $x1 + $width;

                //coordinates after transformation are rounded by Zend
                $page->drawImage($image, $x1, $y1, $x2, $y2);

                $this->y = $y1 - 50;
            }
        }
    }

    protected function insertText(&$page, $store = null){
        $top = 800;
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.2));
        $this->_setFontBold($page, 10);
        $page->drawText(__('TAX INVOICE'), 25, $top, 'UTF-8');
    }

    protected function insertAddress(&$page, $store = null)
    {
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0.05));
        $font = $this->_setFontBold($page, 8);
        $page->setLineWidth(0);
        $this->y = $this->y ? $this->y : 815;
        $top = 800;
        $values = explode(
            "\n",
            $this->_scopeConfig->getValue(
                'sales/identity/address',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store
            )
        );
        foreach ($values as $value) {
            if ($value !== '') {
                $value = preg_replace('/<br[^>]*>/i', "\n", $value);
                foreach ($this->string->split($value, 45, true, true) as $_value) {
                    $page->drawText(
                        trim(strip_tags($_value)),
                        $this->getAlignCenter($_value, 285, 440, $font, 10),
                        $top,
                        'UTF-8'
                    );
                    $top -= 10;
                }
            }
        }
        $this->y = $this->y > $top ? $top : $this->y;
    }

    protected function lastSection($page,$signatureStartY,$order,$currentHeight,$invoice){

        /* Signature Start */
        $this->_setFontBold($page, 7.3);
        $page->drawText(__('For RSH Global Private Limited'), 379, $signatureStartY, 'UTF-8');
        $this->_setFontBold($page, 7.5);
        $page->drawText(__('Authorized Signatory'),400, $signatureStartY-40,'UTF-8' );

        /* Signature end */

        /* Amount text */
        $page->drawText(__('Amount Chargeable (in words):'), 25, $signatureStartY-20, 'UTF-8');

        $number = round($invoice->getGrandTotal());
        $no = floor($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array('0' => '', '1' => 'One', '2' => 'Two',
            '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
            '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
            '13' => 'Thirteen', '14' => 'Fourteen',
            '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
            '18' => 'Eighteen', '19' =>'Nineteen', '20' => 'Twenty',
            '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
            '60' => 'Sixty', '70' => 'Seventy',
            '80' => 'Eighty', '90' => 'Ninety');
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? 'and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . $hundred;
            } else $str[] = null;
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ?
            "." . $words[$point / 10] . " " .
            $words[$point = $point % 10] : '';
        if($point ==0) {
            $page->drawText($result . "Rupees", 155, $signatureStartY - 20, 'UTF-8');
        }else {
            $page->drawText($result . "Rupees" . $points . " Paise", 155, $signatureStartY - 20, 'UTF-8');
        }
        /* Amount End */

        /* End Description start */
        $desc1 = '1. All Disputes are subjected to Kolkata jurisdiction only. 2. We hereby certify that our registration certificate is';
        $desc2 = 'valid on the date of issue of this invoice. 3. Certified that the particulars given above are true and correct.';
        $page->setFillColor(new \Zend_Pdf_Color_GrayScale(0));
        $this->_setFontBold($page, 8);
        $decStartY = $signatureStartY-60;
        $page->drawText($desc1, 25, $signatureStartY-67, 'UTF-8');
        $page->drawText($desc2, 25, $signatureStartY-77, 'UTF-8');
        /* End Description End */

        $page->drawLine(25, $signatureStartY-82, 570, $signatureStartY-82);

        $this->insertQrCode($page, $order, $currentHeight,$signatureStartY);

    }

    /**
     * Set font as regular
     *
     * @param \Zend_Pdf_Page $object
     * @param int $size
     * @return \Zend_Pdf_Resource_Font
     */
    protected function _setFontRegular($object, $size = 7)
    {
        $font = \Zend_Pdf_Font::fontWithPath(
            $this->_rootDirectory->getAbsolutePath('lib/internal/dejavu-sans/ttf/DejaVuSansCondensed.ttf')
        );
        $object->setFont($font, $size);
        return $font;
    }

    /**
     * Set font as bold
     *
     * @param \Zend_Pdf_Page $object
     * @param int $size
     * @return \Zend_Pdf_Resource_Font
     */
    protected function _setFontBold($object, $size = 7)
    {
        $font = \Zend_Pdf_Font::fontWithPath(
            $this->_rootDirectory->getAbsolutePath('lib/internal/dejavu-sans/ttf/DejaVuSansCondensed-Bold.ttf')
        );
        $object->setFont($font, $size);
        return $font;
    }

    /**
     * Set font as italic
     *
     * @param \Zend_Pdf_Page $object
     * @param int $size
     * @return \Zend_Pdf_Resource_Font
     */
    protected function _setFontItalic($object, $size = 7)
    {
        $font = \Zend_Pdf_Font::fontWithPath(
            $this->_rootDirectory->getAbsolutePath('lib/internal/dejavu-sans/ttf/DejaVuSansCondensed.ttf')
        );
        $object->setFont($font, $size);
        return $font;
    }
}
