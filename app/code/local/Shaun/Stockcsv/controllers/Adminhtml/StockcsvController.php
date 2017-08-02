<?php

class Shaun_Stockcsv_Adminhtml_StockcsvController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $model = Mage::getModel('stockcsv/observer');
        $model->processStock();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('stockcsv')->__('Stock csv file updated')
        );

        $this->_redirectReferer();
    }
}
