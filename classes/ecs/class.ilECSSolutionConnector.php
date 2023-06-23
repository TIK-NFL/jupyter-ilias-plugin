<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/WebServices/ECS/classes/class.ilECSConnector.php';
include_once './Services/WebServices/ECS/classes/class.ilECSConnectorException.php';

/*
 * Handler for ecs subparticipant ressources
 * 
 */
class ilECSSolutionConnector extends ilECSConnector
{
	const RESOURCE_PATH = '/numlab/solutions';
	
	/**
	 * Constructor
	 * @param ilECSSetting $settings 
	 */
	public function __construct(ilECSSetting $settings = null)
	{
		parent::__construct($settings);
	}
	
	
	/**
	 * Add subparticipant
	 * @param ilECSSubParticipant $sub
	 * @param type $a_mid
	 */
	public function addSolution($sol, $a_receiver_com)
	{
		
		ilLoggerFactory::getLogger('jupyter')->debug('Add new solution ressource for subparticipant: ' . print_r($a_receiver_com,true));
		ilLoggerFactory::getLogger('jupyter')->debug(print_r($sol,true));

	 	$this->path_postfix = self::RESOURCE_PATH;
	 	
	 	try 
	 	{
	 		$this->prepareConnection();

			$this->addHeader('Content-Type', 'application/json');
			$this->addHeader('Accept', 'application/json');
			$this->addHeader(ilECSConnector::HEADER_MEMBERSHIPS, $a_receiver_com);

			$this->curl->setOpt(CURLOPT_HTTPHEADER, $this->getHeader());
			$this->curl->setOpt(CURLOPT_HEADER,TRUE);
	 		$this->curl->setOpt(CURLOPT_POST,TRUE);
			
			if(strlen($sol))
			{
				$this->curl->setOpt(CURLOPT_POSTFIELDS, $sol);
			}
			else
			{
				$this->curl->setOpt(CURLOPT_POSTFIELDS, json_encode(NULL));
			}
			$ret = $this->call();
			$info = $this->curl->getInfo(CURLINFO_HTTP_CODE);
	
			ilLoggerFactory::getLogger('jupyter')->debug('Checking HTTP status');
			if($info != self::HTTP_CODE_CREATED)
			{
				ilLoggerFactory::getLogger('jupyter')->error('Cannot create solution, did not receive HTTP 201.');
				ilLoggerFactory::getLogger('jupyter')->info(print_r($ret, true));
				
				throw new ilECSConnectorException('Received HTTP status code: '.$info);
			}
			ilLoggerFactory::getLogger('jupyter')->info('Received HTTP 201: created');

			$eid =  ilJupyterUtil::fetchEContentIdFromHeader($this->curl->getResponseHeaderArray());
			
			// store new ressource
			$ressource = new ilECSJupyterRessource();
			$ressource->setRessourceId($eid);
			$ressource->setRessourceType(ilECSJupyterRessource::RES_SOLUTION);
			$ressource->create();
			
			return $eid;
	 	}
	 	catch(ilCurlConnectionException $exc)
	 	{
	 		throw new ilECSConnectorException('Error calling ECS service: '.$exc->getMessage());
	 	}
		
	}
	
	/**
	 * Delete sub participant
	 * @param type $a_sub_id
	 */
	public function deleteSolution($a_sol_id)
	{
		ilLoggerFactory::getLogger('jupyter')->debug('Delete solution with id '. $a_sol_id);
	 	$this->path_postfix = self::RESOURCE_PATH;
	 	
	 	if($a_sol_id)
	 	{
	 		$this->path_postfix .= ('/'.(int) $a_sol_id);
	 	}
	 	else
	 	{
	 		throw new ilECSConnectorException('Error calling exercise: No solution id given.');
	 	}
	
	 	try 
	 	{
	 		$this->prepareConnection();
	 		$this->curl->setOpt(CURLOPT_CUSTOMREQUEST,'DELETE');
			$res = $this->call();
			return new ilECSResult($res);
	 	}
	 	catch(ilCurlConnectionException $exc)
	 	{
	 		throw new ilECSConnectorException('Error calling ECS service: '.$exc->getMessage());
	 	}
	}
	
	/**
	 * Add Header
	 * @param string $a_name
	 * @param string $a_value
	 * @deprecated
	 */
	public function addHeader($a_name,$a_value): void
	{
		if(is_array($a_value))
		{
			$header_value = implode(',', $a_value);
		}
		else
		{
			$header_value = $a_value;
		}
		parent::addHeader($a_name, $header_value);
	}
	
}