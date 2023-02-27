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
        $post = new Zend_Controller_Request_Http();
        $data = $post->getRawBody();
        $data = json_decode($data, true);

        Mage::log( var_export( $data ,true) , Zend_Log::DEBUG , 'mageshop_bulluno_postback.log', true);

        $orderId = null;
        $status = null;

        if(isset($data['transaction']) && count($data['transaction']) > 0){
            $orderId = $data['transaction']['details'];
            $status = $data['transaction']['status'];
            $this->comment = $data['transaction']['reason'];
        }else if(isset($data['bankslip']) && count($data['bankslip']) > 0){
            $orderId = $data['bankslip']['document_code'];
            $status = $data['bankslip']['status'];
        }
        
        if (empty($orderId) || empty($status)) {
            return false;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        switch ($status) {
            case self::BL_STATUS_PAID:
                $this->_paid($order);
            break;
            case self::BL_STATUS_CC_ANALYSIS:
                $this->_review($order);
            break;
            case self::BL_STATUS_CC_CLIENT_ANALYSIS:
                $this->_holded($order);
            break;
            case self::BL_STATUS_REFUSED:
            case self::BL_STATUS_EXPIRED:
            case self::BL_STATUS_INACTIVATED:
            case self::BL_STATUS_CANCELLED:
            case self::BL_STATUS_BL_CL_BY_DEADLINE:
            case self::BL_STATUS_BL_CL_BY_REQUEST:
            case self::BL_STATUS_BL_CL_REQUEST:
            case self::BL_STATUS_CC_EXPIRED_USER_ANALYSIS:
                $this->_cancelled($order);
            break;
        }

    }

}
