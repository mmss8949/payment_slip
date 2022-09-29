<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

//====目前連哪一台主機====
/*
		icloud為正式機
	*/
$gstrServer = "icloud";

$pos = strpos($_SERVER['SERVER_NAME'], 'icloud.dyu.edu.tw');
if ($pos === false) {      //不是icloud
  $pos2 = strpos($_SERVER['SERVER_NAME'], 'icloudtest.dyu.edu.tw');
  if ($pos2 === false)  //不是icloudtest.dyu.edu.tw
    $gstrServer = "test";
  else
    //icloudtest.dyu.edu.tw
    $gstrServer = "icloud";
} else
  //icloud.dyu.edu.tw
  $gstrServer = "icloud";


//外部傳進列印起迄
$json = file_get_contents('php://input');
$paymentData = json_decode($json, true);

# Get JSON as a string
if ($gstrServer == "icloud")
  $client = new SoapClient("https://icloudtest.dyu.edu.tw/ws/ws_payment_slip.wsdl", array('cache_wsdl' => WSDL_CACHE_NONE));
else
  $client = new SoapClient("https://icloudtest.dyu.edu.tw/ws/ws_payment_slip_test.wsdl", array('cache_wsdl' => WSDL_CACHE_NONE));


try {
  $jsonStr = $client->wsf_get_payment_slip($paymentData['data']);

  $jsonObj = json_decode($jsonStr);
  $jsonData = $jsonObj->data;

  $result = 1;
  $msg = "sucess!";
  $data = $jsonData;
} catch (SoapFault $exception) {

  $result = 0;
  $msg = "連接web service失敗 " . $exception;
  $data = [];
}

$paymentData =  array(
  'result' => $result,
  'msg' => $msg,
  'data' => $data
);

echo json_encode($paymentData);
