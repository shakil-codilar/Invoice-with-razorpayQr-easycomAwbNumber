<?php

namespace Codilar\QrCode\Model\ResourceModel\QrCode;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Codilar\QrCode\Model\QrCode as Model;
use Codilar\QrCode\Model\ResourceModel\QrCode as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
