<?php

class MageShop_Belluno_WebhookController extends MageShop_Belluno_Controller_AbstractController {

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

    public function postbackAction()
    {
        try {
            /**
             * Recebe uma atualização em json
             */
            $post = new Zend_Controller_Request_Http();
            $data = $post->getRawBody();
            $data = json_decode($data, true);
            Mage::log( var_export( $data ,true) , Zend_Log::DEBUG , 'mageshop_bulluno_postback.log', true);
            
            $orderId = null;
            $status = null;
            
            if(isset($data['transaction']) && count($data['transaction']) > 0){
                $transactionId = $data['transaction']['transaction_id'];
                $orderId = $data['transaction']['details'];
            }else if(isset($data['bankslip']) && count($data['bankslip']) > 0){
                $transactionId = $data['bankslip']['id'];
                $orderId = $data['bankslip']['document_code'];
            }
    
            if (empty($orderId)){
                return false;
            }
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

            if(!$order){
                return false;
            }

            /**
             * Pega o code_payment_method
             */
            $payment = $order->getPayment();
            $method = $payment->getMethod();
            $uri = $this->_methodPayment($method, $transactionId);
            $api = $this->getConnector();

            /**
             * Volta na belluno e confere esse pedido
             */
            $resBelluno = json_decode($api->doRequest('', "GET", $uri), true);
            /**
             * Gera um log do resultado
             */
            Mage::log( var_export( $resBelluno ,true) , Zend_Log::DEBUG , 'mageshop_bulluno_postback_callback.log', true);

            $status = null;
            /**
             * pega o status de retorno
             */
            if(isset($resBelluno['transaction']) && count($resBelluno['transaction']) > 0){
                $status = $resBelluno['transaction']['status'];
                $this->comment = $resBelluno['transaction']['reason'];
            }else if(isset($resBelluno['bankslip']) && count($resBelluno['bankslip']) > 0){
                $status = $resBelluno['bankslip']['status'];
            }

            if($status == null){
                return false;
            }
            /**
             * Atualiza o pedido no magento
             */
            switch ($status) {
                case self::BL_STATUS_PAID:
                    parent::payments($resBelluno);
                    $this->_paid($order, $status);
                break;
                case self::BL_STATUS_CC_ANALYSIS:
                case self::BL_STATUS_CC_CLIENT_ANALYSIS:
                    $this->_holded($order, $status);
                break;
                case self::BL_STATUS_REFUSED:
                case self::BL_STATUS_EXPIRED:
                case self::BL_STATUS_INACTIVATED:
                case self::BL_STATUS_CANCELLED:
                case self::BL_STATUS_BL_CL_BY_DEADLINE:
                case self::BL_STATUS_BL_CL_BY_REQUEST:
                case self::BL_STATUS_BL_CL_REQUEST:
                case self::BL_STATUS_CC_EXPIRED_USER_ANALYSIS:
                    $this->_cancelled($order, $status);
                break;
            }
        } catch (\Exception $e) {
           Mage::log( var_export( $e ,true) , Zend_Log::DEBUG , 'mageshop_bulluno_error_postback.log', true);
           return false;
        }
    }

    private function _methodPayment($key, $transaction_id)
    {
      switch ($key) {
        case 'belluno_creditcard':
          return "/v2/transaction/{$transaction_id}";
        case 'belluno_bankslip':
          return "/v2/bankslip/{$transaction_id}";
        case 'belluno_pix':
          return "/v2/transaction/{$transaction_id}/pix";
      }
    }
    
    /**Function to return class connector for requests */
    private function getConnector()
    {
        return new MageShop_Belluno_Service_ApiBelluno();
    }
}
