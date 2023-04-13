<?php

namespace Codilar\QrCode\Model;

use Magento\Framework\Model\AbstractModel;
use Codilar\QrCode\Model\ResourceModel\QrCode as ResourceModel;

class QrCode extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }
}
