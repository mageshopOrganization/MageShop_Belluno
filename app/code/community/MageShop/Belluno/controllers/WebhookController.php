<?php

class MageShop_Belluno_WebhookController extends Mage_Core_Controller_Front_Action {

    public function postbackAction()
    {
        $statusCancelBoleto = array("Closure by deadline", "Closure by request", "Closure requested");
        $post = new Zend_Controller_Request_Http();
        $data = $post->getRawBody();
        $data = json_decode($data, true);
        $comment = '';
        Mage::log( var_export( $data ,true) , Zend_Log::DEBUG , 'mageshop_bulluno_postback.log', true);
        $orderId = null;
        $status = null;
        if(isset($data['transaction']) && count($data['transaction']) > 0){
            $orderId = $data['transaction']['details'];
            $status = $data['transaction']['status'];
            $comment = $data['transaction']['reason'];
        }else if(isset($data['bankslip']) && count($data['bankslip']) > 0){
            $orderId = $data['bankslip']['document_code'];
            $status = $data['bankslip']['status'];
        }
        if (empty($orderId) || empty($status)) {
        return false;
        }
        if ($status == 'Paid') {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        $status = $order->getStatus();
        // Check if the order can be invoiced
        if(!$order->canInvoice()) {
            Mage::log( "Id: " . $orderId . " // The order cannot be invoiced.", Zend_Log::DEBUG , 'invoices-bulluno.log', true);
            return true;
        }
        // Create the invoice
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $invoice->addComment('Atualização Belluno: ' . Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'), false, true);
            $invoice->save();
            $transactionSave->save();
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $order->save();
        }
        if (isset($status) && strlen($status) > 1 && $status == 'Refused') {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->canCancel()) {
                return true;
            }
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->save();
        } else if (isset($status) && strlen($status) > 1 && $status == 'Expired') {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->canCancel()) {
                return true;
            }  
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->save();
        } else if (isset($status) && strlen($status) > 1 && $status == 'Inactivated') {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->canCancel()) {
                return true;
            }
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->save();
        } else if (isset($status) && strlen($status) > 1 && $status == 'Cancelled') {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->canCancel()) {
                return true;
            }  
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->save();
        } else if (isset($status) && strlen($status) > 1 &&  in_array($status, $statusCancelBoleto)) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!$order->canCancel()) {
                return true;
            }
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
            $history = $order->addStatusHistoryComment($comment, true);
            $history->setIsCustomerNotified(false); 
            $order->save();
        }
    }
}
