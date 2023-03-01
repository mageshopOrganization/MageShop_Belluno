<?php

class MageShop_Belluno_Model_Observer
{
    /**
     * Function to cancel order
     */
    public function _cancelOrder(Varien_Event_Observer $observer)
    {
        if ($observer->getOrder()->isCanceled()) {
            Mage::log( "Cancelamento Belluno Payment" , Zend_Log::DEBUG , 'mageshop_bulluno_cancel.log', true);
            $order = $observer->getOrder();
            $payment = $order->getPayment();
            $method = $payment->getMethod();

            if ($method == 'belluno_creditcard') {
                $connector = $this->getConnector();
                $value = $payment->getAdditionalInformation('value');
                $transactionId = $payment->getAdditionalInformation('transaction_id');

                $request = [
                    'amount' => $value,
                    'reason' => '2'
                ];
                $request = json_encode($request);
                $connector->doRequest($request, "POST", "/transaction/$transactionId/refund");
            }
        }
    }

    /**
     * Function to handle order refund
     */
    public function handleOrderRefund(Varien_Event_Observer $observer)
    {
        Mage::log( "Reembolso Belluno Payment" , Zend_Log::DEBUG , 'mageshop_bulluno_refund.log', true);
        $creditmemo = $observer->getCreditmemo();
        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();
        $method = $payment->getMethod();

        if ($method == 'belluno_creditcard') {
            $connector = $this->getConnector();
            $value = $creditmemo->getGrandTotal();
            $transactionId = $payment->getAdditionalInformation('transaction_id');

            $request = [
                'amount' => $value,
                'reason' => '3'
            ];
            $request = json_encode($request);
            $connector->doRequest($request, "POST", "/transaction/$transactionId/refund");
        }
    }
}