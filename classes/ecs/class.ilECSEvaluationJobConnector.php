<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/WebServices/ECS/classes/class.ilECSConnector.php';
include_once './Services/WebServices/ECS/classes/class.ilECSConnectorException.php';

/*
 * Handler for ecs subparticipant ressources
 * 
 */
class ilECSEvaluationJobConnector extends ilECSConnector
{
	const RESOURCE_PATH = '/numlab/evaluation_jobs';
	
	private $jupyter_settings = null;
	
	/**
	 * Constructor
	 * @param ilECSSetting $settings 
	 */
	public function __construct(ilECSSetting $settings = null)
	{
		parent::__construct($settings);
		
		$this->jupyter_settings = ilJupyterSettings::getInstance();
		
	}
	
	/**
	 * @return ilJupyterSettings
	 */
	public function getJupyterSettings()
	{
		return $this->jupyter_settings;
	}
	
	
	/**
	 * Add subparticipant
	 * @param ilECSSubParticipant $sub
	 * @param type $a_mid
	 */
	public function addEvaluationJob(ilECSEvaluationJob $a_evaluation_job, $a_targets)
	{
		ilLoggerFactory::getLogger('jupyter')->debug('Add new evaluation job ressource for ' . print_r($a_targets,true));

	 	$this->path_postfix = self::RESOURCE_PATH;
	 	
	 	try 
	 	{
	 		$this->prepareConnection();

			$this->addHeader('Content-Type', 'application/json');
			$this->addHeader('Accept', 'application/json');
			$this->addHeader(ilECSConnector::HEADER_MEMBERSHIPS, $a_targets);

			$this->curl->setOpt(CURLOPT_HTTPHEADER, $this->getHeader());
			$this->curl->setOpt(CURLOPT_HEADER,TRUE);
	 		$this->curl->setOpt(CURLOPT_POST,TRUE);
			
			$this->curl->setOpt(CURLOPT_POSTFIELDS, $a_evaluation_job->getJson());
			
			
			$ret = $this->call();
			$info = $this->curl->getInfo(CURLINFO_HTTP_CODE);
	
			ilLoggerFactory::getLogger('jupyter')->debug('Checking HTTP status...');
			if($info != self::HTTP_CODE_CREATED)
			{
				ilLoggerFactory::getLogger('jupyter')->error('Cannot create evaluation job ressource, did not receive HTTP 201');
				ilLoggerFactory::getLogger('jupyter')->error('Return value: '. print_r($ret, true));
				throw new ilECSConnectorException('Received HTTP status code: '.$info);
			}
			ilLoggerFactory::getLogger('jupyter')->debug('... got HTTP 201 (created)');

			$eid =  ilJupyterUtil::fetchEContentIdFromHeader($this->curl->getResponseHeaderArray());
			return $eid;
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