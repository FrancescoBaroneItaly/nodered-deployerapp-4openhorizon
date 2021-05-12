# NodeRed-deployerapp-4openhorizon

  Sample deployer App for NodeRed instances registered to OPENHORIZON

## Quick Start

  Follow the steps in order to:
    
	- Create the service in the catalog services
	- Register a node into IEAM

## Build Service and Register to Catalog
      
	  Requisite:
	    
		Install IEAM horizon agent without register the node ( optional )
		
      git clone https://github.com/FrancescoBaroneItaly/nodered-4openhorizon.git
	  
	  cd nodered-4openhorizon
	  
	  *I used the IBMCLOUD container registry to store the image
	  
	  export BASE_IMAGE_NAME=nodered-deployer
	  export REGISTRY_NAMESPACE=ibm-edgelab
	  export CLOUD_API_KEY=****************************
	  export ARCH=$(hzn architecture)
 
	  CREATE The SERVICE in the central HUB
	  
	  //CREATE SERVICE
      hzn dev service new -s $BASE_IMAGE_NAME -V 1.0.0 -i us.icr.io/$REGISTRY_NAMESPACE/$BASE_IMAGE_NAME --noImageGen
	  
	  The Customize service definition
	  
	  //CUSTOM  horizon/service.definition.json
		{
			"org": "$HZN_ORG_ID",
			"label": "$SERVICE_NAME for $ARCH",
			"description": "",
			"public": true,
			"documentation": "",
			"url": "$SERVICE_NAME",
			"version": "$SERVICE_VERSION",
			"arch": "$ARCH",
			"sharable": "multiple",
			"requiredServices": [],
			"userInput": [
				{
				"name": "SERVICE_TYPE",
				"label": "Define the type of service",
				"type": "string",
				"defaultValue": "nodered-deployer"
				}
			],
			"deployment": {
				"services": {
					"$SERVICE_NAME": {
						"image": "${DOCKER_IMAGE_BASE}_$ARCH:$SERVICE_VERSION",
						"privileged": false,
						"ports": [{"HostPort":"5000:80/tcp","HostIP":"0.0.0.0"}]
					}
				}
			}
		}

	  To use automated deployer application service, it's necessary to map nodered UI console to an HOST Port
	  
	  
	  Now Build the Image ( follow IBM Infocenter for more details )
	  
	      eval $(hzn util configconv -f horizon/hzn.json)
		  
		  docker build --rm=true -t "us.icr.io/$REGISTRY_NAMESPACE/${BASE_IMAGE_NAME}_$ARCH:$SERVICE_VERSION" .
		  
	  If build succefully, publish service to IEAM catalog
	  
	     hzn exchange service publish -r "us.icr.io:iamapikey:$CLOUD_API_KEY" -f horizon/service.definition.json


	  
## Create a Deployment service policy

  Create a deployment policy for the just created service starting from this JSON file
  
	  {
		"label": "$SERVICE_NAME.deployment-policy",
		"description": "Policy for nodered app service",
		"service": {
		  "name": "$SERVICE_NAME",
		  "org": "$HZN_ORG_ID",
		  "arch": "$ARCH",
		  "serviceVersions": [
			{
			  "version": "$SERVICE_VERSION",
			  "priority": {
				"priority_value": 1,
				"retries": 2,
				"retry_durations": 600
			  },
			  "upgradePolicy": {}
			}
		  ],
		  "nodeHealth": {}
		},
		"constraints": [
		  "nodered-deployernode == edgenode03"
		],
		"userInput": [
		  {
			"serviceOrgid": "$HZN_ORG_ID",
			"serviceUrl": "$SERVICE_NAME",
			"serviceArch": "$ARCH",
			"inputs": [				
				]
		  }
		  
		]
	  }

     hzn exchange deployment addpolicy --json-file=FILE.JSON [policy name]
  
## Register a edge node using policy
  Add node properties to identify the node, create a json file
  
     {
		"properties": [				
		{ "name": "nodered-deployernode", "value": "edgenode03" },
		{ "name": "nodered-deployment", "value": "edgenode03" }
		],
		"constraints": []
	 }

   Register the node
   
      hzn register --policy=node.policy.json
	  
## add daemon.json config for docker @ host
  
      vi /etc/docker/daemon.json
	    
		{
		"dns": ["8.8.8.8", "1.1.1.1"]
		}

## Connecting to DEPLOYER App
  
  http://edge_host:5000  