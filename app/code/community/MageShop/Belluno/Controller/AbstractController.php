<?php 

class MageShop_Belluno_Controller_AbstractController extends Mage_Core_Controller_Front_Action{

    const BL_STATUS_PAID = "Paid";
    const BL_STATUS_EXPIRED = "Expired";
    const BL_STATUS_INACTIVATED = "Inactivated";
    const BL_STATUS_CANCELLED = "Cancelled";
    const BL_STATUS_REFUSED = "Refused";
    const BL_STATUS_BL_CL_BY_DEADLINE = "Closure by deadline";
    const BL_STATUS_BL_CL_BY_REQUEST= "Closure by request";
    const BL_STATUS_BL_CL_REQUEST = "Closure requested";
    const BL_STATUS_CC_ANALYSIS = 'Manual Analysis';
    const BL_STATUS_CC_CLIENT_ANALYSIS = 'Client Manual Analysis';
    const BL_STATUS_CC_EXPIRED_USER_ANALYSIS = 'Expired User Analysis';
    protected $comment = '';

    protected function setReason($comment)
    {
        $this->comment = $comment;
    }
    protected function getReason(){
        return $this->comment;
    }

    protected function _paid($order, $statusBelluno){
        // Check if the order can be invoiced
        if(!$order->canInvoice()) {
            return true;
        }
        // Atualize o campo setAdditionalInformation do pedido
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        $additionalInformation['status'] = $statusBelluno;
        $payment->setAdditionalInformation($additionalInformation);

        $commentInvoice = 'Gateway Belluno Digital: ' . Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s') . ' | '. $this->getReason();
        // Create the invoice
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $invoice->addComment($commentInvoice, true, true);
        $invoice->save();
        $transactionSave->save();

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING, true);
        $order->save();
    }

    protected function _holded($order, $statusBelluno)
    {
        if ($order->getStatus() == 'holded') {
            return $this;
        }
        // Atualize o campo setAdditionalInformation do pedido
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        $additionalInformation['status'] = $statusBelluno;
        $payment->setAdditionalInformation($additionalInformation);

        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        $order->setStatus('holded');
        $order->addStatusHistoryComment($this->getReason(), false);
        $order->save();
    }

    protected function _review($order, $statusBelluno){
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
           return $this;
        }
        // Atualize o campo setAdditionalInformation do pedido
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        $additionalInformation['status'] = $statusBelluno;
        $payment->setAdditionalInformation($additionalInformation);

        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        $order->setStatus('payment_review');
        $order->addStatusHistoryComment($this->getReason(), false);
        $order->save();
    }

    protected function _cancelled($order, $statusBelluno){
        $comment = 'Gateway Belluno Digital: ' . Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s') . ' | ' . $this->getReason();
        if (!$order->canCancel()) {
            return true;
        }
       // Atualize o campo setAdditionalInformation do pedido
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        $additionalInformation['status'] = $statusBelluno;
        $payment->setAdditionalInformation($additionalInformation);

        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
        $history = $order->addStatusHistoryComment($comment, true);
        $history->setIsCustomerNotified(true); 
        $order->save();
    }

    protected function _refund($order, $statusBelluno){
        if (!$order->canCreditmemo()) {
            return true;
        }
        // Atualize o campo setAdditionalInformation do pedido
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        $additionalInformation['status'] = $statusBelluno;
        $payment->setAdditionalInformation($additionalInformation);

        $service = Mage::getModel('sales/service_order', $order);
        $creditmemo = $service->prepareCreditmemo();
        $creditmemo->setRefundRequested(true);
        $creditmemo->setOfflineRequested(true);
        $creditmemo->register();
        $creditmemo->save();

        $order->addStatusHistoryComment($this->getReason(), true);
        $order->save();
    }

}