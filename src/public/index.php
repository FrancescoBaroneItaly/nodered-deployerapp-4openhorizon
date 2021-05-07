<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function createPath($path) {
    if (is_dir($path)) return true;
    $prev_path = substr($path, 0, strrpos($path, '/', -2) + 1 );
    $return = createPath($prev_path);
    return ($return && is_writable($prev_path)) ? mkdir($path) : false;
	}

// autoload files
require '../vendor/autoload.php';
require '../config.php';

// configure Slim application instance
// initialize application
$app = new \Slim\App($config);

// initialize dependency injection container
$container = $app->getContainer();

// add view renderer to DI container
$container['view'] = function ($container) {
  return new \Slim\Views\PhpRenderer("../views/");
};

//setting objcts
$container['ieam_api'] = function($container) {
	
	$config = $container->get('settings');
	
	$filename = "/tmp/ieam_config.json";
  
	if (file_exists($filename)) {
	  
	  $config_contents = file_get_contents($filename);
	  $json = json_decode($config_contents,true);
	  
	  $config['ieam_api'] = array( 'baseurl' => $json["ieam_baseurl"], 'username' => $json["ieam_username"], 'password' => $json["ieam_password"]);
	  }
	  
	return $config['ieam_api'];
	};

$container['nodered_deployment_path'] = function($container) {
	
	$config = $container->get('settings');
	return $config['nodered_deployment_path'];
	};


function checkConfig(){
		
	$filename = "/tmp/ieam_config.json";
  
	if (file_exists($filename)) {
		
		return true;
		}
		
	return false;
	}
	
// ---------------------------
// welcome page controller
//ROOT
$app->get('/', function (Request $request, Response $response) {
  return $response->withHeader('Location', $this->router->pathFor('home'));
});

//HOME
$app->get('/home', function (Request $request, Response $response) {
	
  $response = $this->view->render($response, 'home.phtml', [
    'router' => $this->router
  ]);
  return $response;
})->setName('home');

//MESSAGE
$app->get('/message', function (Request $request, Response $response) {
	
  $response = $this->view->render($response, 'page-message.phtml', [
    'router' => $this->router, 'title' => '', 'message' => ''
  ]);
  return $response;
})->setName('page-message');


//CONFIG
$app->get('/config', function (Request $request, Response $response) {
  
  $filename = "/tmp/ieam_config.json";
  
  if (file_exists($filename)) {
	  
	  $config = file_get_contents($filename);
	  $json = json_decode($config,true);
	  }
	  
  $response = $this->view->render($response, 'config.phtml', [
    'router' => $this->router, 'ieam_baseurl' => $json["ieam_baseurl"], 'ieam_username' => $json["ieam_username"], 'ieam_password' => $json["ieam_password"]
  ]);
  return $response;
})->setName('config');

$app->post('/config/save', function (Request $request, Response $response, $args) {
  
  //var_dump($args);  
  $form_data = $request->getParsedBody();
  
  //----------------------------------------------
  $filename = "/tmp/ieam_config.json";
  
  $json = json_encode(array('ieam_baseurl' => $form_data["ieam_baseurl"], 'ieam_username' => $form_data["ieam_username"], 'ieam_password' => $form_data["ieam_password"], 'created' => date("Y-m-d h:i:sa") ));
    
  file_put_contents($filename, $json); 
	
  //----------------------------------------------
  
  $response = $this->view->render($response, 'config.phtml', [
    'router' => $this->router
  ]);
  return $response;
})->setName('config-save');

//NODERED
$app->get('/nodered', function (Request $request, Response $response) {
  
  if(!checkConfig()){
  
    $response = $this->view->render($response, 'page-message.phtml', [
		'router' => $this->router, 'title' => 'Missing Config', 'message' => 'First config IEAM API Endpoint to access to API services'
		]);
		
	return $response;
	}
	
  //$username='wwsc/iamapikey';
  //$password='nejoeMsK9_5GBA5EoHgNcatH3pvzUJU0Iu945ORlSCzc';
  //$URL='https://cp-console.ieam42-edge-8e873dd4c685acf6fd2f13f4cdfb05bb-0000.us-south.containers.appdomain.cloud/edge-exchange/v1/catalog/wwsc/services';
  
  $t1=round(microtime(true) * 1000);
  
  //var_dump($this->ieam_api);  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Cache-Control: no-cache';
	
  echo("<script>console.log('PHP WS TARGET: " . $this['ieam_api']['baseurl'] . "');</script>");
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/catalog/wwsc/services");
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
  
  $services=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  
  $time_namelookup = curl_getinfo($ch,   CURLINFO_NAMELOOKUP_TIME );
  $time_connect = curl_getinfo($ch,   CURLINFO_CONNECT_TIME  );
  $time_appconnect = curl_getinfo($ch,  CURLINFO_APPCONNECT_TIME );
  $time_pretransfer = curl_getinfo($ch,   CURLINFO_PRETRANSFER_TIME );
  $time_redirect = curl_getinfo($ch,   CURLINFO_REDIRECT_TIME );
  $time_starttransfer = curl_getinfo($ch,   CURLINFO_STARTTRANSFER_TIME  );
  $time_total = curl_getinfo($ch,   CURLINFO_TOTAL_TIME );
  
  echo("<script>console.log('PHP WS CURLINFO_NAMELOOKUP_TIME: " . $time_namelookup  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_CONNECT_TIME: " . $time_connect  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_APPCONNECT_TIME: " . $time_appconnect  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_PRETRANSFER_TIME: " . $time_pretransfer  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_REDIRECT_TIME: " . $time_redirect  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_STARTTRANSFER_TIME: " . $time_starttransfer  . "');</script>");
  echo("<script>console.log('PHP WS CURLINFO_TOTAL_TIME: " . $time_total  . "');</script>");
  
  curl_close ($ch);
  
  $t2=round(microtime(true) * 1000);
  echo("<script>console.log('PHP WS TIMING: " . ($t2-$t1) . "');</script>");
  
  $_services = json_decode($services, true);
  //var_dump($_services);
  
  $_services = $_services["services"];
  
  $services = array();
  foreach($_services as $_service) {
	
	//---user inputs
	$userInputs = $_service["userInput"];
	
	$isNodered=false;
	foreach($userInputs as $userInput) {
		
		if($userInput["name"]=="SERVICE_TYPE" && $userInput["defaultValue"]=="nodered"){
			
			//---deployment
			$_deployment = $_service["deployment"];
			$deployment = json_decode($_deployment, true);
			//var_dump($deployment);
			$url = $_service["url"];
			$tmp = $deployment["services"];
			$tmp = $tmp[$url];
			$ports = $tmp["ports"];
			//echo "PORTS";
			foreach($ports as $port){
				
				//var_dump($port);
				$HostPort = $port["HostPort"];
				
				//echo "HOST_PORT=".$HostPort;
				$port = explode(":", $HostPort);
				
				if (isset($port[0])){
					
					//echo "PORT=".$port[0];
					$servicePort = $port[0];
					
					$isNodered=true;
					}
				}
			}
		}
		
	if($isNodered){
		
		$deployer_node = $_ENV["HZN_DEVICE_ID"];
		
		$t1=round(microtime(true) * 1000);
		
		//------------------------
		//modify node_policy
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/nodes/".$deployer_node."/policy");
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
		curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
		
		$_policy=curl_exec ($ch);    	
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
		curl_close ($ch);
		
		$t2=round(microtime(true) * 1000);
		echo("<script>console.log('PHP WS TIMING: " . ($t2-$t1) . "');</script>");
		
		if($status_code==200){
			
			$policy = json_decode($_policy, true);			
			//var_dump($_policy);
			
			$new_properties = array();
			$properties = $policy["properties"];
			
			foreach($properties as $property){
				
				//var_dump($property);
				//$property = json_decode($_property, true);				
				if($property["name"]== $_service["url"]."-runtime"){
					
					//echo "NODERED=".$property["value"];
					$property["value"]="0";
					}
					
				array_push($new_properties, $property);
				}
				
			//update policy
			
			$new_policy = array();
			$new_policy["properties"] = $new_properties;
			$new_policy["constraints"] = $policy["constraints"];			
			
			ob_start();
  
			echo json_encode($new_policy);
			$post = ob_get_contents();
    
			ob_end_clean();
			
			//var_dump($post);
			$t1=round(microtime(true) * 1000);
			
			$headers = [];
			$headers[] = "Authorization: Basic {$credentials}";
			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Cache-Control: no-cache';
			$headers[] = 'Content-Length: '.strlen($post);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/nodes/".$deployer_node."/policy");
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");			
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
			curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
			
			curl_exec ($ch);    	
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
			curl_close ($ch);
			
			$t2=round(microtime(true) * 1000);
			echo("<script>console.log('PHP WS TIMING: " . ($t2-$t1) . "');</script>");
			//echo "POLICY ".$status_code;
			}
		//------------------------
		
		$service = array();
		array_push($service, $_service["url"]);
		array_push($service, $_service["version"]);
		array_push($service, $_service["arch"]);
		array_push($service, $_service["owner"]);
		array_push($service, $_service["description"]);			
		array_push($service, $this->router->pathFor('node-service', array('url' => htmlspecialchars($_service['url'], ENT_COMPAT, 'UTF-8'), 'version' => $_service["version"], 'arch' => $_service["arch"] )) );
				
		array_push($services, $service);
		}
	/*
	$properties = $_node["properties"];
	
	foreach($properties as $property) {
		
		if($property["name"]=="nodered-v2-runtime" && $property["value"]=="1"){
			
			//trovato node con nodered runtime
			$node= array();
			array_push($node, $_node["name"]);
			array_push($node, $_node["orgid"]);
			array_push($node, $_node["nodeType"]);
			array_push($node, $_node["owner"]);
			array_push($node, $this->router->pathFor('nodeservice', array('id' => htmlspecialchars($_node['name'], ENT_COMPAT, 'UTF-8'), 'service' => 'nodered' )) );
			
			array_push($nodes, $node);
			}
		}
	*/
	}
	
  //$nodes = [["aaa","bbb","ccc","ddd",""]];
  
  $response = $this->view->render($response, 'nodered.phtml', [
    'router' => $this->router, 'services' => $services
  ]);
  return $response;
})->setName('nodered');

// NODERED-SERVICE
$app->get('/node-service/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
    
  //var_dump($args);
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  } 
  
  //var_dump($this->ieam_api);  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/node-details");
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
  
  $nodes=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  $_nodes = json_decode($nodes, true);
  //var_dump($_nodes);
  
  $nodes = array();
  foreach($_nodes as $_node) {
	  
	$properties = $_node["properties"];
	
	foreach($properties as $property) {
		
		if($property["name"]==$url."-runtime" && $property["value"]=="1" ){
			
			//verifica se ha il servizio a bordo
			$services = $_node["services"];
			
			$hasService=false;
			foreach($services as $service) {
				
				if( $service["serviceUrl"]== $url && 
					$service["version"]== $version &&
					$service["arch"]== $arch )$hasService=true;
				}
				
			if($hasService){
				
				//trovato node con nodered runtime
				$node= array();
				array_push($node, $_node["name"]);
				array_push($node, $_node["orgid"]);
				array_push($node, $_node["nodeType"]);
				array_push($node, $_node["owner"]);
				array_push($node, $this->router->pathFor('nodered-data', 
					array( 'node' => htmlspecialchars($_node['name'], ENT_COMPAT, 'UTF-8'), 
					   'url' => htmlspecialchars($url, ENT_COMPAT, 'UTF-8'), 
					   'version' => $version, 
					   'arch' => $arch )
					   ) );
							
				array_push($nodes, $node);
				}
			}
		}
		
	}
	
  $response = $this->view->render($response, 'nodered-hosts.phtml', [
    'router' => $this->router, 'nodes' => $nodes
  ]);
  return $response;
})->setName('node-service');

// ---------------------------

// NODERED-SERVICE
$app->get('/nodered-data/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
    
  //var_dump($args);
  
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
	
  $deployments = array();
  
  //USING MMS
  $object_type = "deployment-".$node."_".$url."_".$version."_".$arch;
  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);
    
  //------------------------
  $deployer_node = $_ENV["HZN_DEVICE_ID"];
  
  //modify node_policy
  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';
		
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/nodes/".$deployer_node."/policy");
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	
  $_policy=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
    
  if($status_code==200){
		
		$policy = json_decode($_policy, true);			
		//var_dump($_policy);
		
		$new_properties = array();
		$properties = $policy["properties"];
		
		foreach($properties as $property){
			
			//var_dump($property);
			//$property = json_decode($_property, true);				
			if($property["name"]== $url."-runtime"){
				
				//echo "NODERED=".$property["value"];
				$property["value"]="2";
				}
				
			array_push($new_properties, $property);
			}
				
		//update policy
		
		$new_policy = array();
		$new_policy["properties"] = $new_properties;
		$new_policy["constraints"] = $policy["constraints"];			
		
		ob_start();
  
		echo json_encode($new_policy);
		$post = ob_get_contents();
    
		ob_end_clean();
		
		//var_dump($post);
		
		$headers = [];
		$headers[] = "Authorization: Basic {$credentials}";
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Cache-Control: no-cache';
		$headers[] = 'Content-Length: '.strlen($post);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/nodes/".$deployer_node."/policy");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");			
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
		curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
		
		curl_exec ($ch);    	
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
		curl_close ($ch);
		
		//echo "POLICY ".$status_code;
		}
  //------------------------
		
  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."?all_objects=true" );
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
  
  //$socket = '/var/run/horizon/essapi.sock';
  //curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
  
  $mms=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  //echo "STATUS CODE = ".$status_code;  
  if($status_code==200){
	
	$_mms = json_decode($mms, true);
	//var_dump($_mms);
	
	$deploy_id = array();
	$deploy_id_active = "";
	
	foreach($_mms as $item) {
		
		if (strpos($item["objectID"], 'deploy') === 0) {
				
			array_push($deploy_id, $item["objectID"]);
			}
		
		if (strpos($item["objectID"], 'active-deploy') === 0) {
			
			//PUT at the beginning the active object
			$deploy_id_temp = array();
			array_push($deploy_id_temp, $item["objectID"]);
			
			foreach($deploy_id as $obj)array_push($deploy_id_temp, $obj);
			$deploy_id = $deploy_id_temp;
			
			// var_dump("ACTIVE ".$item);
			// active-deploy_xxxxx
			$deploy_id_active = substr($item["objectID"],14);
			}
		//$json = json_encode(array('deploy_id' => $item["deploy_id"], 'version' => $item["version"], 'type' => 'type', 'description' => $item["description"], 'date' => '2021'));
		
		//array_push($deployments, $json);
		}
	
	$deploy_activate_date = "---";
		
	//var_dump($deploy_id);
	foreach($deploy_id as $item) {
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/".$item."/data");
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
		curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
		
		//$socket = '/var/run/horizon/essapi.sock';
		//curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
		
		$deploy=curl_exec ($ch);    	
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
		curl_close ($ch);
		
		if($status_code==200){
			
			$deploy = json_decode($deploy, true);
			//var_dump($deploy);
			
			//echo "CHECK "."active-deploy_".$deploy_id_active."/".$item."  ";
			$tmp="active-deploy_".$deploy_id_active;
			if ( $item == $tmp) {
														
				//extract activate date
				$deploy_activate_date = $deploy["activate_date"];
				//echo "DATE ".$deploy_activate_date;
				
			}else{
							
				$json = array();
				if($deploy_id_active==$deploy["deploy_id"] && $deploy["deploy_id"]!=""){				
					array_push($json,"<div class='deploy_active'>".$deploy_activate_date."</div>");				
				}else{				
					array_push($json,"");
					}
					
				array_push($json, $deploy["deploy_id"]);
				array_push($json, $deploy["version"]);
				array_push($json, $deploy["deploy_type"]);
				array_push($json, $deploy["description"]);
				array_push($json, $deploy["deploy_date"]);			
				array_push($json, $deploy["deploy_update"]);			
				array_push($json, $this->router->pathFor('nodered-prebuild', array(
						'object_id' => $deploy["deploy_id"],
						'node' => $node,
						'url' => $url, 
						'version' => $version, 
						'arch' =>  $arch)
						));
			
				array_push($json, '');
				
				//var_dump($json);
				array_push($deployments, $json);
				}
			}
		}
	}
    
  /*
  USING FILESYSTEM
  $folder = "/".$node."_".$url."_".$version."_".$arch;
  
  createPath($this['nodered_deployment_path'].$folder);
  
  $deployment_files = glob($this['nodered_deployment_path'].$folder."/*.json");
  
  foreach($deployment_files as $filename) {
	
	$deployment_file_content = file_get_contents($filename);
	$json = json_decode($deployment_file_content,true);
	
	array_push($deployments, $json);
	}
	
  */
  /*
  $filename = $this['nodered_deployment_path'].$folder."/".$node."_".$url."_".$version."_".$arch.".json";
  
  if (!is_file($filename)) {
	
	$default_contents = array();
	
	$json = json_encode(array('data' => $default_contents, 'created' => date("Y-m-d h:i:sa") ));
    
	file_put_contents($filename, $json); 
	}
	
  $deployment_file = file_get_contents($filename);
  $json = json_decode($deployment_file,true);
	
  $deployments = $json["data"];
  */
  $response = $this->view->render($response, 'nodered-data.phtml', [
    'router' => $this->router, 'deploy_id_active' => $deploy_id_active, 'deployments' => $deployments, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node
  ]);
  
  return $response;
})->setName('nodered-data');
	
// ---------------------------
	
$app->get('/deployments/add/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
  
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
  
  $response = $this->view->render($response, 'nodered-deployment-add.phtml', [
    'router' => $this->router, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node
  ]);
  return $response;
})->setName('nodered-deployment-add');

$app->post('/deployments/add/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
  
  //var_dump($args);  
    
  //----------------------------------------------
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
  
  //----------------------------------------------
  
  //var_dump($_FILES);
  //----------------------------------------------
  
  $form_data = $request->getParsedBody();
  
  $array_deploy = array('deploy_id' => $form_data["deploy_id"], 
						'version' => $form_data["deploy_name"], 
						'description' => $form_data["deploy_description"], 
						'console_username' => $form_data["deploy_username"], 
						'console_password' => $form_data["deploy_password"], 
						'deploy_date' => date("Y-m-d H:i:s"), 
						'deploy_type' => 'NodeRed' );
						
  //$array_files  = array('deploy_id' => $form_data["deploy_id"]);
  
  $files = array();
  
  if(count($_FILES["item_file"]['name'])>0){
	  
	for($j=0; $j < count($_FILES["item_file"]['name']); $j++){ 
		
		//loop the uploaded file array
		$filename = $_FILES["item_file"]['name']["$j"]; //file name
		if($filename!=""){
			$filecontent = base64_encode(file_get_contents($_FILES["item_file"]['tmp_name']["$j"]));
			
			$array_deploy[$filename]=$filecontent;
			array_push($files, $filename);
			}
		}
	}
	
  $array_deploy["files"]=$files;
  
  $json = json_encode($array_deploy);
  
  //var_dump($json);
  //echo "\n\n";
  
  ob_start();
  
  echo $json;
  $out = ob_get_contents();
  
  ob_end_clean();
    
  $byte_array = unpack('C*', $out);
  //echo "DATA ".$byte_array."\n";
  
  //USING MMS
  $object_id   = "deploy_".$form_data["deploy_id"];
  $object_type = "deployment-".$node."_".$url."_".$version."_".$arch;
    
  //echo "OBJECT=".$object_type."/".$object_id;
  $meta = json_encode(array('objectID' => $object_id, 'objectType' => $object_type));
  
  $data_array = "[";  
  $i=0;
  foreach ($byte_array as $value) {
	
	if($i>0)$data_array=$data_array.",";
	$i=1;
	
	$data_array=$data_array.$value;
	}
	
  $data_array=$data_array."]";
  
  ob_start();
  
  echo "{\"data\": ".$data_array.", \"meta\": ".$meta." }";
  $post = ob_get_contents();
    
  ob_end_clean();
  /*
  $post = json_encode( array(
	
	"data" => $byte_array,
	"meta" => $meta	
	));

  */	
  
  //....
  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Cache-Control: no-cache';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/".$object_id );
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
  
  $mms=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  //echo "STATUS CODE = ".$status_code;  
  
  //----------------------------------------------
  
  $response = $this->view->render($response, 'nodered-deployment-add.phtml', [
    'router' => $this->router, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node
  ]);
  return $response;
})->setName('nodered-deployment-add');

// ---------------------------

// NODERED-SERVICE PRE BUILD
$app->get('/nodered-prebuild/{object_id}/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
  
  //----------------------------------------------
  $object_id = filter_var($args['object_id'], FILTER_SANITIZE_STRING);
  if (empty($object_id)) {
    throw new Exception('ERROR: object_id is not valid');
  } 
  
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
  
  //----------------------------------------------
  //get data object
  
  //USING MMS
  $object_type = "deployment-".$node."_".$url."_".$version."_".$arch;
  
  //echo("<script>console.log('PHP: [".$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/deploy_".$object_id."/data]". "');</script>");  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);
  
  //------------------------
  
  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';
		
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/deploy_".$object_id."/data" );  
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	
  $_mms=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  //echo("<script>console.log('PHP: " . $status_code . "');</script>");
  //echo "STATUS CODE =".$status_code;
  if($status_code==200){
	
	$mms = json_decode($_mms, true);
	
	//var_dump($mms);
	
	//--------------------------------
	//oggetto link
	$content = json_encode(array('DEPLOY_TARGET_ID' => $object_id, 
								 'DEPLOY_TARGET_TYPE' => $object_type
								));
	
	$deploy_target_link = tempnam("/tmp", "deploy_target_link.json");	
	$handle = fopen($deploy_target_link, "w");
	fwrite($handle, $content);
	fclose($handle);
	
	//crea oggetto deploy.tar for deployer app node

	$rnd_string = bin2hex(random_bytes(10)); 
	$a = new PharData("/tmp/deploy.".$rnd_string.".tar");
	
	//write deploy.json	
	$content = json_encode(array('DEPLOY_ID' => $object_id, 
								 'DEPLOY_TYPE' => $object_type, 
								 'DEPLOY_DESCRIPTION' => $mms["description"],
								 'DEPLOY_DATE' => $mms["deploy_date"],
								 'DEPLOY_OWNER' => 'docker image',
								 'DEPLOY_PRESERVELOCAL' => 'false',
								 'DEPLOY_USERNAME' => $mms["console_username"], 
								 'DEPLOY_PASSWORD' => $mms["console_password"],
								 'DEPLOY_FLOWS' => 'flows.json',
								 'DEPLOY_CREDENTIALS'=> 'flows_cred.json',
								 'BUILD_MODE' => 'true',
								 'DEPLOY_TARGET_LINK' => base64_encode($deploy_target_link),								 
								 'DEPLOY_TARGET_PORT' => '5000',
								 'DEPLOY_INITIAL' => base64_encode($_mms)
								 ));
	
	$deploy_file = tempnam("/tmp", "deploy.json");	
	$handle = fopen($deploy_file, "w");
	fwrite($handle, $content);
	fclose($handle);
	
	// ADD FILES TO archive.tar FILE
	$a->addFile($deploy_file, "deploy.json");	
	unlink($deploy_file);
	
	//write files settings.js, flows.json, flow_cred.json ...
	$files = array();	
	foreach($mms["files"] as $filename){
		
		//echo $filename." ";
		
		$content = base64_decode($mms[$filename]);
		
		$files[$filename] = tempnam("/tmp", $filename);
		$handle = fopen($files[$filename], "w");
		fwrite($handle, $content);
		fclose($handle);
		
		// ADD FILES TO archive.tar FILE
		$a->addFile($files[$filename], $filename);		
		
		unlink($files[$filename]);
		}
	//---------------------------------------------
	
	//publish object to MMS deployer node
	$filecontent = file_get_contents("/tmp/deploy.".$rnd_string.".tar");	
	$byte_array = unpack('C*', $filecontent);
		
	//USING MMS
	ob_start();
	
	$deployer_node = $_ENV["HZN_DEVICE_ID"];
	
	echo "{";	
	echo "  \"objectID\": \"".$deployer_node.".".$url."-deployment\",";
	echo "  \"objectType\": \"deploy.tar\",";
	echo "  \"destinationOrgID\": \"wwsc\",";
	echo "  \"destinationPolicy\": {";
	echo "	\"properties\": [],";
	echo "	\"constraints\": [";
	echo "		\"".$url."-deployment == ".$deployer_node."\"";
	echo "		],";
	echo "	\"services\": [";
	echo "	  {";
	echo "		\"orgID\": \"wwsc\",";
	echo "		\"arch\": \"".$arch."\",";
	echo "		\"serviceName\": \"".$url."\",";
	echo "		\"version\": \"".$version."\"";
	echo "	  }";
	echo "	]";
	echo "  },";
	echo "  \"version\": \"".$mms["version"]."\",";
	echo "  \"description\": \"Version ".$mms["deploy_date"]."\",";
	echo "  \"expiration\": \"\",";
	echo "  \"activationTime\": \"\"";
	echo "}";

	$meta = ob_get_contents();
	ob_end_clean();
	
	$data_array = "[";  
	$i=0;
	foreach ($byte_array as $value) {
		
		if($i>0)$data_array=$data_array.",";
		$i=1;
		
		$data_array=$data_array.$value;
		}
		
	$data_array=$data_array."]";
	 
	ob_start();
	 
	echo "{\"data\": ".$data_array.", \"meta\": ".$meta." }";
	$post = ob_get_contents();
		
	ob_end_clean();
	/*
	 $post = json_encode( array(
		
		"data" => $byte_array,
		"meta" => $meta	
		));

	 */	
	 
	//var_dump($post);
	//....
	 
	$credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

	$headers = [];
	$headers[] = "Authorization: Basic {$credentials}";
	$headers[] = 'Content-Type: application/json';
	$headers[] = 'Cache-Control: no-cache';

	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/deploy.tar/edgenode01.nodered-v2-deployment" );
	curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/deploy.tar/".$deployer_node.".".$url."-deployment" );	
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
	curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	 
	$mms=curl_exec ($ch);    	
	$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
	curl_close ($ch);
	 
	//echo "STATUS CODE BUILD OBJECT = ".$status_code;  
	//----------------------------------------------
	//delete file
	
	//----------------------------------------------
	}
	  
  //----------------------------------------------
  
  $response = $this->view->render($response, 'nodered-prebuild.phtml', [
    'router' => $this->router, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node, 'object_id' => $object_id, 'deployer_node' => $deployer_node
	]);
  
  return $response;
})->setName('nodered-prebuild');

// NODERED-SERVICE UPLOAD OBJECT
$app->put('/nodered-upload_deployment/{deploy_target_link}/data', function (Request $request, Response $response, $args) {
	
	$deploy_target_link = filter_var($args['deploy_target_link'], FILTER_SANITIZE_STRING);	
	if (empty($deploy_target_link)) {
      throw new Exception('ERROR: deploy_target_link is not valid');
	  } 
  	  
	$json = json_encode($request->getParsedBody());
	$json = json_decode($json);
	$json->{'deploy_update'} = date("Y-m-d H:i:s");
	//array_push($json, array("deploy_update" => date("Y-m-d H:i:s") ) );
	$json = json_encode($json);
	
	ob_start();
  
	echo $json;
	$out = ob_get_contents();
	  
	ob_end_clean();
	  
	//echo "BODY";
	//var_dump($out);
	
	$deploy_target_link = base64_decode($deploy_target_link);
	$deploy_target = file_get_contents($deploy_target_link);
	$deploy_json = json_decode($deploy_target,true);
	
	$object_id = "deploy_".$deploy_json["DEPLOY_TARGET_ID"];
	$object_type = $deploy_json["DEPLOY_TARGET_TYPE"];
	
	$byte_array = unpack('C*', $out);
	//echo "DATA ".$byte_array."\n";
	 
	 //USING MMS
		
	echo "UPLOAD OBJECT=".$object_type."/".$object_id;
	$meta = json_encode(array('objectID' => $object_id, 'objectType' => $object_type));
	 
	$data_array = "[";  
	$i=0;
	foreach ($byte_array as $value) {
		
		if($i>0)$data_array=$data_array.",";
		$i=1;
		
		$data_array=$data_array.$value;
		}
		
	$data_array=$data_array."]";
	 
	ob_start();
	 
	echo "{\"data\": ".$data_array.", \"meta\": ".$meta." }";
	$post = ob_get_contents();
		
	ob_end_clean();
	 /*
	 $post = json_encode( array(
		
		"data" => $byte_array,
		"meta" => $meta	
		));

	 */	
	 
	//....
	 
	$credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

	$headers = [];
	$headers[] = "Authorization: Basic {$credentials}";
	$headers[] = 'Content-Type: application/json';
	$headers[] = 'Cache-Control: no-cache';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/".$object_id );
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
	curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	 
	$mms=curl_exec ($ch);    	
	$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
	curl_close ($ch);
	 
	echo "STATUS CODE = ".$status_code;  
	 
	//----------------------------------------------
  
	$response = $response->withStatus(200);
	
	return $response;
})->setName('nodered-upload_deployment');
	
// NODERED-SERVICE CHECK DEPLOYMENT
$app->get('/nodered-check_deployment/{object_type}/{object_id}/{deployer_node}', function (Request $request, Response $response, $args) {
	
	$object_id = filter_var($args['object_id'], FILTER_SANITIZE_STRING);	
	if (empty($object_id)) {
      throw new Exception('ERROR: object_id is not valid');
	  } 
  
	$object_type = filter_var($args['object_type'], FILTER_SANITIZE_STRING);
    if (empty($object_type)) {
	  throw new Exception('ERROR: object_type is not valid');
	  } 
	
	$deployer_node = filter_var($args['deployer_node'], FILTER_SANITIZE_STRING);
    if (empty($deployer_node)) {
	  throw new Exception('ERROR: deployer_node is not valid');
	  }
	
	//------------------------
	$credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);
  
	//------------------------
	
	$headers = [];
	$headers[] = "Authorization: Basic {$credentials}";
	$headers[] = 'Content-Type: application/x-www-form-urlencoded';
	$headers[] = 'Cache-Control: no-cache';
		
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/".$object_id."/destinations" );  
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
	curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	
	$_status=curl_exec ($ch);    	
	$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
	curl_close ($ch);
	
	echo("<script>console.log('PHP: " . $status_code . "');</script>");
	
	//PRECONDITION
	$response = $response->withStatus(404);
	
	//echo "STATUS CODE =".$status_code;	
	if($status_code==200){
		
		$status = json_decode($_status, true);
		var_dump($status);
		
		foreach($status as $destination){
			
			if($destination["destinationID"]==$deployer_node && $destination["destinationType"]=="openhorizon.edgenode" && $destination["status"]=="delivered"){
				
				$response = $response->withStatus(200);
				}
			}
		}
	 
	return $response;
})->setName('nodered-check_deployment');

// NODERED-SERVICE BUILD
$app->get('/nodered-build/{object_id}/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
 
  //----------------------------------------------
  $object_id = filter_var($args['object_id'], FILTER_SANITIZE_STRING);
  if (empty($object_id)) {
    throw new Exception('ERROR: object_id is not valid');
  } 
  
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
  
  //----------------------------------------------
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);

  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/json';
  $headers[] = 'Cache-Control: no-cache';

  $ch = curl_init();
  //echo "/edge-exchange/v1/orgs/wwsc/services/".$url."_".$version."_".$arch;
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-exchange/v1/orgs/wwsc/services/".$url."_".$version."_".$arch);
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
  
  $services=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  $_services = json_decode($services, true);
  //var_dump($_services);
  
  $_services = $_services["services"];
  $servicePort="0";
  
  foreach($_services as $_service) {
	
	//---user inputs
	$userInputs = $_service["userInput"];
	
	$isNodered=false;
	foreach($userInputs as $userInput) {
		
		if($userInput["name"]=="SERVICE_TYPE" && $userInput["defaultValue"]=="nodered"){
			
			//---deployment
			$_deployment = $_service["deployment"];
			$deployment = json_decode($_deployment, true);
			//var_dump($deployment);
			$url = $_service["url"];
			$tmp = $deployment["services"];
			$tmp = $tmp[$url];
			$ports = $tmp["ports"];
			//echo "PORTS";
			foreach($ports as $port){
				
				//var_dump($port);
				$HostPort = $port["HostPort"];
				
				//echo "HOST_PORT=".$HostPort;
				$port = explode(":", $HostPort);
				
				if (isset($port[0])){
					
					//echo "PORT=".$port[0];
					$servicePort = $port[0];
					
					$isNodered=true;
					}
				}
			}
		}
		
	}
  
  //----------------------------------------------
  
  $response = $this->view->render($response, 'nodered-build.phtml', [
    'router' => $this->router, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node, 'object_id' => $object_id, 'servicePort' => $servicePort
	]);
  
  return $response;
})->setName('nodered-build');

// --------------------------

$app->post('/deployments/activate/{node}/{url}/{version}/{arch}', function (Request $request, Response $response, $args) {
  
  //var_dump($args);  
    
  //----------------------------------------------
  $node = filter_var($args['node'], FILTER_SANITIZE_STRING);
  if (empty($node)) {
    throw new Exception('ERROR: node is not valid');
  }  
  
  $url = filter_var($args['url'], FILTER_SANITIZE_STRING);
  if (empty($url)) {
    throw new Exception('ERROR: url is not valid');
  }  
  
  $version = filter_var($args['version'], FILTER_SANITIZE_STRING);
  if (empty($version)) {
    throw new Exception('ERROR: version is not valid');
  } 
  
  $arch = filter_var($args['arch'], FILTER_SANITIZE_STRING);
  if (empty($arch)) {
    throw new Exception('ERROR: arch is not valid');
  }
  
  //----------------------------------------------
  
  //var_dump($_FILES);
  //----------------------------------------------
  
  $form_data = $request->getParsedBody();
  
  //var_dump($form_data);
  
  //----------------------------------------------
  //crea oggetto deploy.tar for deployer app node
  $object_id = $form_data["object_id"];
  $object_type = $form_data["object_type"];
  
  //echo("<script>console.log('PHP: [".$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/deploy_".$object_id."/data]". "');</script>");  
  $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);
  
  //------------------------
  
  $headers = [];
  $headers[] = "Authorization: Basic {$credentials}";
  $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  $headers[] = 'Cache-Control: no-cache';
		
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/deploy_".$object_id."/data" );  
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
  curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
	
  $_mms=curl_exec ($ch);    	
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
  curl_close ($ch);
  
  //echo("<script>console.log('PHP: " . $status_code . "');</script>");
  //echo "STATUS CODE =".$status_code;
  if($status_code==200){
	
	$mms = json_decode($_mms, true);
	
	//var_dump($mms);
	
	//--------------------------------
    $rnd_string = bin2hex(random_bytes(10)); 
    $a = new PharData("/tmp/deploy.".$rnd_string.".tar");
    
    //write deploy.json	
    $content = json_encode(array('DEPLOY_ID' => $object_id, 
  							 'DEPLOY_TYPE' => $object_type, 
  							 'DEPLOY_DESCRIPTION' => $mms["description"],
  							 'DEPLOY_DATE' => $mms["deploy_date"],
  							 'DEPLOY_OWNER' => 'docker image',
  							 'DEPLOY_PRESERVELOCAL' => 'false',
  							 'DEPLOY_USERNAME' => $mms["console_username"], 
  							 'DEPLOY_PASSWORD' => $mms["console_password"],
  							 'DEPLOY_FLOWS' => 'flows.json',
  							 'DEPLOY_CREDENTIALS'=> 'flows_cred.json'
  							 ));
    
    $deploy_file = tempnam("/tmp", "deploy.json");	
    $handle = fopen($deploy_file, "w");
    fwrite($handle, $content);
    fclose($handle);
    
    // ADD FILES TO archive.tar FILE
    $a->addFile($deploy_file, "deploy.json");	
    unlink($deploy_file);
    
    //write files settings.js, flows.json, flow_cred.json ...
    $files = array();	
    foreach($mms["files"] as $filename){
  	
  	//echo $filename." ";
  	
  	$content = base64_decode($mms[$filename]);
  	
  	$files[$filename] = tempnam("/tmp", $filename);
  	$handle = fopen($files[$filename], "w");
  	fwrite($handle, $content);
  	fclose($handle);
  	
  	// ADD FILES TO archive.tar FILE
  	$a->addFile($files[$filename], $filename);		
  	
  	unlink($files[$filename]);
  	}
    //---------------------------------------------
    
    //publish object to MMS deployer node
    $filecontent = file_get_contents("/tmp/deploy.".$rnd_string.".tar");	
    $byte_array = unpack('C*', $filecontent);
  	
    //USING MMS
    ob_start();
    
    //$deployer_node = $_ENV["HZN_DEVICE_ID"];
    
    echo "{";	
    echo "  \"objectID\": \"".$node.".".$url."-deployment\",";
    echo "  \"objectType\": \"deploy.tar\",";
    echo "  \"destinationOrgID\": \"wwsc\",";
    echo "  \"destinationPolicy\": {";
    echo "	\"properties\": [],";
    echo "	\"constraints\": [";
    echo "		\"".$url."-deployment == ".$node."\"";
    echo "		],";
    echo "	\"services\": [";
    echo "	  {";
    echo "		\"orgID\": \"wwsc\",";
    echo "		\"arch\": \"".$arch."\",";
    echo "		\"serviceName\": \"".$url."\",";
    echo "		\"version\": \"".$version."\"";
    echo "	  }";
    echo "	]";
    echo "  },";
    echo "  \"version\": \"".$mms["version"]."\",";
    echo "  \"description\": \"Version ".$mms["deploy_date"]."\",";
    echo "  \"expiration\": \"\",";
    echo "  \"activationTime\": \"\"";
    echo "}";
    
    $meta = ob_get_contents();
    ob_end_clean();
    
    $data_array = "[";  
    $i=0;
    foreach ($byte_array as $value) {
  	
  	if($i>0)$data_array=$data_array.",";
  	$i=1;
  	
  	$data_array=$data_array.$value;
  	}
  	
    $data_array=$data_array."]";
     
    ob_start();
     
    echo "{\"data\": ".$data_array.", \"meta\": ".$meta." }";
    $post = ob_get_contents();
  	
    ob_end_clean();
    /*
     $post = json_encode( array(
  	
  	"data" => $byte_array,
  	"meta" => $meta	
  	));
    
     */	
     
    //var_dump($post);
    //....
     
    $credentials = base64_encode($this['ieam_api']['username'].":".$this['ieam_api']['password']);
    
    $headers = [];
    $headers[] = "Authorization: Basic {$credentials}";
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Cache-Control: no-cache';
    
    $ch = curl_init();
    //curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/deploy.tar/edgenode01.nodered-v2-deployment" );
    curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/deploy.tar/".$node.".".$url."-deployment" );	
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
    curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
     
    $mms=curl_exec ($ch);    	
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
    curl_close ($ch);
  	 
    //echo "STATUS CODE BUILD OBJECT = ".$status_code;  
  	
	//----------------------------------------------
	//ACTIVE DEPLOY
	ob_start();
  
    echo "{\"deployerapp\":1,\"activate_date\":\"".date("Y-m-d H:i:s")."\"}";
    $out = ob_get_contents();
  
    ob_end_clean();
    
    $byte_array = unpack('C*', $out);
    //  echo "DATA ".$byte_array."\n";
  
    //USING MMS    
    //echo "OBJECT=".$object_type."/".$object_id;
    $meta = json_encode(array('objectID' => "active-deploy_".$object_id, 'objectType' => $object_type));
  
    $data_array = "[";  
    $i=0;
    foreach ($byte_array as $value) {
	
	  if($i>0)$data_array=$data_array.",";
	  $i=1;
	
	  $data_array=$data_array.$value;
	  }
	
    $data_array=$data_array."]";
  
    ob_start();
  
    echo "{\"data\": ".$data_array.", \"meta\": ".$meta." }";
    $post = ob_get_contents();
    
    ob_end_clean();
  
    $ch = curl_init();    
    curl_setopt($ch, CURLOPT_URL,$this['ieam_api']['baseurl']."/edge-css/api/v1/objects/wwsc/".$object_type."/active-deploy_".$object_id );  
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post );
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
    curl_setopt($ch, CURLOPT_CAINFO, "../horizon.crt");
     
    $mms=curl_exec ($ch);    	
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code  
    curl_close ($ch);
    //----------------------------------------------
	}
  
  return $response->withHeader('Location', $this->router->pathFor('nodered-data', array( 'node' => $node, 'url' => $url, 'version' => $version, 'arch' => $arch ) ) );
  /*
  $response = $this->view->render($response, 'nodered-data.phtml', [
    'router' => $this->router, 'url' => $url, 'arch' => $arch, 'version' => $version, 'node' => $node
  ]);
  
  return $response;
  */
})->setName('nodered-deployment-activate');

  
// --------------------------

$app->run();

?>