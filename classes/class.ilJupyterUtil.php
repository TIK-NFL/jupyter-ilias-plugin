<?php
/**
 * Jupyter plugin definition 
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 */
class ilJupyterUtil
{
	/**
	 * Get ecs community id by mid
	 *
	 * @param ilECSSetting $server
	 * @param int $a_mid
	 * @return int
	 */

	public static function extractJsonFromCustomZip($a_zip_string)
	{
		ilLoggerFactory::getLogger('jupyter')->debug('Trying to decode ' . $a_zip_string);
		
		// check if custom zip format
		if (substr($a_zip_string, 0, 4) != 'ZIP:')
		{
			ilLoggerFactory::getLogger('jupyter')->debug('No custom zip format given.');
			return $a_zip_string;
		}
		
		$zip_cleaned = substr($a_zip_string, 4);
		ilLoggerFactory::getLogger('jupyter')->dump($zip_cleaned, ilLogLevel::DEBUG);
		
		// base64 decode
		$decoded = base64_decode($zip_cleaned);
		
		// save to temp file
		$tmp_name = ilFileUtils::ilTempnam();
		file_put_contents($tmp_name, $decoded);
		
		$zip = new ZipArchive();
		if ($zip->open($tmp_name) === true)
		{
			ilLoggerFactory::getLogger('jupyter')->debug('Successfully decoded zip');
			$json = $zip->getFromName('json');
			ilLoggerFactory::getLogger('jupyter')->dump($json, ilLogLevel::DEBUG);
			
			unlink($tmp_name);
			return $json;
		}
		else
		{
			ilLoggerFactory::getLogger('jupyter')->warning('Failed opening zip archive');
		}
		return; 
	}

	/**
	 * @param array $a_header HTTP headers
	 * @return int EContentId
	 * @throws ilECSConnectorException
	 *
	 * TODO: Borrowed from class.ilECSConnector.php:_fetchEContentIdFromHeader due to visibility.
	 */
	public static function fetchEContentIdFromHeader(array $a_header): int
	{
		$location_parts = [];
		foreach ($a_header as $header => $value) {
			if (strcasecmp('Location', $header) === 0) {
				$location_parts = explode('/', $value);
				break;
			}
		}
		if (!$location_parts) {
			ilLoggerFactory::getLogger('jupyter')->error(__METHOD__ . ': Cannot find location headers.');
			throw new ilECSConnectorException("Cannot find location header in response");
		}
		if (count($location_parts) === 1) {
			ilLoggerFactory::getLogger('jupyter')->warning(__METHOD__ . ': Cannot find path seperator.');
			throw new ilECSConnectorException("Location header has wrong format: " . $location_parts[0]);
		}
		$econtent_id = end($location_parts);
		ilLoggerFactory::getLogger('jupyter')->info(__METHOD__ . ': Received EContentId ' . $econtent_id);
		return (int) $econtent_id;
	}
}