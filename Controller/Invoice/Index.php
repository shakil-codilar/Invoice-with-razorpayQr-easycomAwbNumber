<?php
namespace Codilar\QrCode\Controller\Invoice;

use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        EncoderInterface $urlEncoder,
        DecoderInterface $urlDecoder,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        $this->_pageFactory = $pageFactory;
        $this->urlEncoder = $urlEncoder;
        $this->urlDecoder = $urlDecoder;
        $this->encryptor = $encryptor;
        $this->resultPageFactory  = $resultPageFactory;
        return parent::__construct($context);
    }

    public function execute()
    {
        $param = $this->getRequest()->getParam('id');
//        $id =$this->urlDecoder->decode($param);
        $id = 0;
        if (!empty($param)) {
            $decryptedHash = $this->urlDecoder->decode($param);
            $decryptedHash = str_replace(" ", "+", $decryptedHash);
            $id = (int)$this->encryptor->decrypt($decryptedHash);

        }
        function count_digit($number)
        {
            return strlen((string) $number);
        }
        $z='';
        for($i=count_digit($id) ;$i<9 ;$i++ ){
            $z .= 0;
        }
        $finalInvoiceId = $z .$id;
        $page = $this->_pageFactory->create();
        $block = $page->getLayout()->getBlock('qrcode_invoice_index');
        $block->setData('finalInvoiceId', $finalInvoiceId);
        return $page;
    }
}
