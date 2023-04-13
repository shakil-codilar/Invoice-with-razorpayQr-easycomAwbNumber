<?php

namespace Codilar\QrCode\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QrCode extends AbstractDb
{

    const TABLE_NAME = 'codilar_qrcode';
    const ID_FIELD_NAME = 'id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD_NAME);
    }
}
