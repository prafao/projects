<?php

class Shaun_Imagescsv_Adminhtml_ImagescsvController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $model = Mage::getModel('imagescsv/observer');
        $model->processImages();

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('imagescsv')->__('Images csv file updated')
        );

        $this->_redirectReferer();
    }
}
