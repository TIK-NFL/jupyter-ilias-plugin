<?php

/**
 * Global Jupyter settings
 */
class ilJupyterSettings
{
    private static $instance = null;

    /**
     * @var ilSetting
     */
    private $storage = null;

    private $log_level;

    private $proxy_url;

    private $jupyterhub_server_url;

    private $api_token;


    /**
     * Singleton constructor
     */
    private function __construct()
    {
        include_once './Services/Administration/classes/class.ilSetting.php';
        $this->storage = new ilSetting('ass_jupyter');
        $this->init();
    }

    /**
     * Get singleton instance
     *
     * @return ilJupyterSettings
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getLogLevel()
    {
        return $this->log_level;
    }

    public function setLogLevel($a_level)
    {
        $this->log_level = $a_level;
    }

    /**
     * @return mixed
     */
    public function getProxyUrl()
    {
        return $this->proxy_url;
    }

    /**
     * @param mixed $proxy_url
     */
    public function setProxyUrl($proxy_url): void
    {
        $this->proxy_url = $proxy_url;
    }

    /**
     * @return mixed
     */
    public function getJupyterhubServerUrl()
    {
        return $this->jupyterhub_server_url;
    }

    /**
     * @param mixed $jupyterhub_server_url
     */
    public function setJupyterhubServerUrl($jupyterhub_server_url): void
    {
        $this->jupyterhub_server_url = $jupyterhub_server_url;
    }

    /**
     * @return mixed
     */
    public function getApiToken()
    {
        return $this->api_token;
    }

    /**
     * @param mixed $api_token
     */
    public function setApiToken($api_token): void
    {
        $this->api_token = $api_token;
    }



    /**
     * Update settings
     */
    public function update()
    {
        $this->getStorage()->set('log_level', (string) $this->log_level);
        $this->getStorage()->set('proxy_url', (string) $this->proxy_url);
        $this->getStorage()->set('jupyterhub_server_url', (string) $this->jupyterhub_server_url);
        $this->getStorage()->set('api_token', (string) $this->api_token);
    }

    /**
     *
     * @return ilSetting
     */
    protected function getStorage()
    {
        return $this->storage;
    }

    /**
     * Init (read) settings
     */
    protected function init()
    {
        $this->log_level = $this->getStorage()->get('log_level', $this->log_level);
        $this->proxy_url = $this->getStorage()->get('proxy_url', $this->proxy_url);
        $this->jupyterhub_server_url = $this->getStorage()->get('jupyterhub_server_url', $this->jupyterhub_server_url);
        $this->api_token = $this->getStorage()->get('api_token', $this->api_token);
    }
}