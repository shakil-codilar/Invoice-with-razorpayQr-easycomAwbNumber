<?php
namespace Codilar\QrCode\Model\Order\Pdf\Items\Invoice;

use Magento\Sales\Model\RtlTextHandler;
use Magento\Catalog\Api\ProductRepositoryInterface;

class DefaultInvoice extends \Magento\Sales\Model\Order\Pdf\Items\Invoice\DefaultInvoice
{
    protected $productRepository;
    public function __construct(\Magento\Framework\Model\Context $context,ProductRepositoryInterface $productRepository, \Magento\Framework\Registry $registry, \Magento\Tax\Helper\Data $taxData, \Magento\Framework\Filesystem $filesystem, \Magento\Framework\Filter\FilterManager $filterManager, \Magento\Framework\Stdlib\StringUtils $string, \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [], ?RtlTextHandler $rtlTextHandler = null)
    {
        parent::__construct($context,$registry, $taxData, $filesystem, $filterManager, $string, $resource, $resourceCollection, $data, $rtlTextHandler);
        $this->productRepository = $productRepository;
    }

    public function draws($count)
    {
        $order = $this->getOrder();
        $item = $this->getItem();

        $pdf = $this->getPdf();
        $page = $this->getPage();
        $lines = [];

        // draw Sr No
        $lines[0][] = [
            'text' =>$count,
            'feed' => 42,
            'align' => 'right',
            'font' => 'bold',
            'font_size' => 8
        ];


        // draw Product name
        $lines[0][] = [
            'text' => $this->string->split($this->getProduct($this->getSku($item))->getName(), 20, true, true),
            'feed' => 55,
            'font' => 'bold',
            'font_size' => 8
        ];

        // draw SKU
        $lines[0][] = [
            'text' => $this->string->split($this->getSku($item), 15),
            'feed' => 216,
            'align' => 'right',
            'font' => 'bold',
            'font_size' => 8
        ];

        // draw HSN Code
        $lines[0][] = [
            'text' => $this->getProduct($this->getSku($item))->getHsnCode(),
            'feed' => 268,
            'align' => 'right',
            'font' => 'bold',
            'font_size' => 8
        ];


        // draw QTY
        $lines[0][] = ['text' => $item->getQty() * 1, 'feed' => 332, 'align' => 'right','font' => 'bold', 'font_size' => 8];

        // draw item Prices
        $i = 0;
        $prices = $this->getItemPricesForDisplay();
        $feedPrice = 313;
        $feedSubtotal = $feedPrice + 170;
        foreach ($prices as $priceData) {
            if (isset($priceData['label'])) {
                // draw Price label
                $lines[$i][] = ['text' => $priceData['label'], 'feed' => $feedPrice, 'align' => 'right'];
                // draw Subtotal label
                $lines[$i][] = ['text' => $priceData['label'], 'feed' => $feedSubtotal, 'align' => 'right'];
                $i++;
            }
            // draw Price
            $lines[$i][] = [
                'text' => $priceData['price'],
                'feed' => $feedPrice,
                'font' => 'bold',
                'align' => 'right',
                'font_size' => 8
            ];
            $i++;
        }
        $itemDiscount =round($item->getBaseDiscountAmount(),4);
        // draw Item Discount
        if ($itemDiscount == 0) {
            $lines[0][] = [
                'text' => $itemDiscount,
                'feed' => 365,
                'font' => 'bold',
                'align' => 'left',
                'font_size' => 8
            ];
        }else{
            $lines[0][] = [
                'text' => '-' . $itemDiscount,
                'feed' => 365,
                'font' => 'bold',
                'align' => 'left',
                'font_size' => 8
            ];
        }

        $taxableValue = round($item->getPrice()* ($item->getQty() * 1)-$itemDiscount,3);
        $taxAmount = round($taxableValue*9/100,3);
        // draw Taxable value
        $lines[0][] = [ 'text' =>$taxableValue , 'feed' => 435, 'font' => 'bold', 'align' => 'right', 'font_size' => 8];

        //draw 1st Rate
        $lines[0][] = [ 'text' =>'9%', 'feed' => 465, 'font' => 'bold', 'align' => 'right', 'font_size' => 8];

        //draw 1st tax amount
        $lines[0][] = [ 'text' =>$taxAmount, 'feed' => 500, 'font' => 'bold', 'align' => 'right', 'font_size' => 8];

        //draw 2nd Rate
        $lines[0][] = [ 'text' =>'9%', 'feed' => 525, 'font' => 'bold', 'align' => 'right', 'font_size' => 8];

        //draw 2nd tax amount
        $lines[0][] = [ 'text' =>$taxAmount, 'feed' => 558, 'font' => 'bold', 'align' => 'right', 'font_size' => 8];

        $lineBlock = ['lines' => $lines, 'height' => 10];

        $page = $pdf->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
        $this->setPage($page);
    }
    public function getProduct(string $sku)
    {
        $childProduct = null;
        try {
            $childProduct = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(__($exception->getMessage()));
        }

        return $childProduct;
    }
}
