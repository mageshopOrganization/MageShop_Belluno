<?php

class MageShop_Belluno_Block_Form_Pix extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mageshop/belluno/form/pix.phtml');
    }
}
