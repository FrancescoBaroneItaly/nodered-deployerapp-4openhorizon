<?php
  
  $username='wwsc/iamapikey';
  $password='nejoeMsK9_5GBA5EoHgNcatH3pvzUJU0Iu945ORlSCzc';
  $URL='https://cp-console.ieam42-edge-8e873dd4c685acf6fd2f13f4cdfb05bb-0000.us-south.containers.appdomain.cloud/edge-exchange/v1/orgs/wwsc/node-details';
	
  echo "DIR ".dirname(__FILE__);
  
  $credentials = base64_encode("$username:$password");

  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$URL);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
  curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  //curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  //curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
  //scurl_setopt($ch, CURLOPT_TLSAUTH_USERNAME, $username);
  //curl_setopt($ch, CURLOPT_TLSAUTH_PASSWORD, $password);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  //curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1 ); 
  curl_setopt($ch, CURLOPT_CAINFO, "/var/www/horizon.crt");
  
  $nodes=curl_exec ($ch);
  echo "2-NODES ".$nodes." | ";
  var_dump(json_decode($nodes, true));
  
  if (curl_errno($ch)) {
	echo 'Error:' . curl_error($ch);
	}

?>
