<?php

class MageShop_Belluno_ConsultController extends Mage_Core_Controller_Front_Action {
  private $resulstApi = [];
  private $error = null;
   /**
   * Function to get postback from belluno @author Vitor <web@tryideas.com.br>
   */
  public function indexAction() {
      $api = $this->getConnector();
      $methodsPayments = $this->getRequest()->getParams();
      if(empty($methodsPayments)){
        $this->error [] = array(
          "res" => false,
          "code" => "401",
          "message" => "Parametr onão encontrado"
        );
      }

      foreach ($methodsPayments as $key => $transaction_id) {
        if($transaction_id){
          $uri = $this->_methodPayment( $key, $transaction_id);
          $this->resulstApi = json_decode($api->doRequest('', "GET", $uri), true);
          Mage::log( var_export( $this->resulstApi ,true) , Zend_Log::DEBUG , date('d-m-Y') . '-bulluno-payment-force-admin.log', true);
          if($this->resulstApi){
            $this->order();
          }else{
            $this->error [$transaction_id] = array(
              "res" => false,
              "code" => "401",
              "message" => "Pedido não encontrado"
            );
          }
        }

      }
      
      if($this->error !== null){
        echo json_encode($this->error);
        return false;
      }
  
      echo json_encode(array(
        "res" => true,
        "code" => "200",
        "message" => "Requisição feita com sucesso"
      ));
      return true;

  }

  private function order(){

    if(isset($this->resulstApi['transaction']) && count($this->resulstApi['transaction']) > 0){
        $orderId = $this->resulstApi['transaction']['details'];
        $statusBelluno = $this->resulstApi['transaction']['status'];
    
    }else if(isset($this->resulstApi['bankslip']) && count($this->resulstApi['bankslip']) > 0){
        $orderId = $this->resulstApi['bankslip']['document_code'];
        $statusBelluno = $this->resulstApi['bankslip']['status'];
    }
    
    if (empty($orderId) || empty($statusBelluno)) {
      $this->error [] = array(
        "res" => false,
        "code" => "402",
        "message" =>'404 $orderId'
      );
      return false;
    }

    $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
    $orderStatusMagento = $order->getStatus();
    
    if($orderStatusMagento == 'canceled' || $orderStatusMagento == 'closed' ){
      return true;
    }

    if( $this->statusPaid($statusBelluno, $orderStatusMagento)  ){
        $history = $order->addStatusHistoryComment( 'Atualização feita pelo painel administrativo', true);
        $history->setIsCustomerNotified(false); 
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);
        $transactionSave = Mage::getModel('core/resource_transaction')
          ->addObject($invoice)
          ->addObject($invoice->getOrder());
        $transactionSave->save();
    }else{
      if( $this->statusRefused($statusBelluno, $orderStatusMagento) ){
          $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
          $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
          $history = $order->addStatusHistoryComment('Atualização feita pelo painel administrativo', true);
          $history->setIsCustomerNotified(false); 
          $order->save();
      }else{
          $history = $order->addStatusHistoryComment('Atualização feita pelo painel administrativo', true);
          $history->setIsCustomerNotified(false); 
          $order->save();
      }
    }
    
    
  }

  /**
   * Verifica se os pedidos estão sicronizados
   *
   * @param string $statusBelluno
   * @param string $orderStatusMagento
   * @return void
   */
  public function statusPaid($statusBelluno, $orderStatusMagento)
  {
    if($statusBelluno == 'Paid'){
      return $orderStatusMagento != 'processing' || $orderStatusMagento != 'complete' ||  $orderStatusMagento != 'closed' ? true : false;
    }
    return false;
  }

  /**
   * Verifica se os pedidos estão sicronizados
   *
   * @param string $statusBelluno
   * @param string $orderStatusMagento
   * @return void
   */
  public function statusRefused($statusBelluno, $orderStatusMagento)
  {
    $statusCancelBoleto = array("Closure by deadline", "Closure by request", "Closure requested");
    if($statusBelluno == 'Refused'){
      return $orderStatusMagento != 'canceled' || $orderStatusMagento != 'closed' ? true : false;
    }
    if($statusBelluno == 'Expired'){
      return $orderStatusMagento != 'canceled' || $orderStatusMagento != 'closed' ? true : false;
    }
    if($statusBelluno == 'Inactivated'){
      return $orderStatusMagento != 'canceled' || $orderStatusMagento != 'closed' ? true : false;
    }
    if($statusBelluno == 'Cancelled'){
      return $orderStatusMagento != 'canceled' || $orderStatusMagento != 'closed' ? true : false;
    }
    if(in_array($statusBelluno, $statusCancelBoleto)){
      return $orderStatusMagento != 'canceled' || $orderStatusMagento != 'closed' ? true : false;
    }
    return false;
  }

  private function _methodPayment($key, $transaction_id)
  {
    switch ($key) {
      case 'belluno_creditcardpayment':
        return "/v2/transaction/{$transaction_id}";
      break;
      case 'belluno_bankslippayment':
        return "/v2/bankslip/{$transaction_id}";
      break;
      case 'belluno_pixpayment':
        return "/v2/transaction/{$transaction_id}/pix";
      break;
    }
  }

  
  /**Function to return class connector for requests */
  private function getConnector()
  {
      return new MageShop_Belluno_Service_ApiBelluno();
  }
}
