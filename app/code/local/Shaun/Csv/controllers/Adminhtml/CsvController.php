<?php

class Shaun_Csv_Adminhtml_CsvController extends Mage_Adminhtml_Controller_Action
{
    public function imagesAction()
    {
        $model = Mage::getModel('csv/images');
        $model->processImages();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('csv')->__('Images csv file updated')
        );

        $this->_redirectReferer();
    }

    public function stockAction()
    {
        $model = Mage::getModel('csv/stock');
        $model->processStock();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('csv')->__('Stock csv file updated')
        );

        $this->_redirectReferer();
    }

    public function attributesAction()
    {
        $model = Mage::getModel('csv/attributes');
        $model->processAttributes();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('csv')->__('Attributes csv file updated')
        );

        $this->_redirectReferer();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/manage_csv_generation');
    }
}
