<?php

/**
 * Global Jupyter settings
 */
class ilJupyterSettings
{
    private static ?ilJupyterSettings $instance = null;

    private ?ilSetting $storage;

    private $log_level;

    private string $proxy_url;

    private string $jupyterhub_server_url;

    private string $jupyterhub_api_path;

    private string $api_token;

    private string $default_jupyter_notebook;


    /**
     * Singleton constructor
     */
    private function __construct()
    {
        include_once './Services/Administration/classes/class.ilSetting.php';
        $this->storage = new ilSetting('ass_jupyter');
        $this->init();
    }

    protected function init()
    {
        $this->log_level = $this->getStorage()->get('log_level', '');
        $this->proxy_url = $this->getStorage()->get('proxy_url', '');
        $this->jupyterhub_server_url = $this->getStorage()->get('jupyterhub_server_url', '');
        $this->jupyterhub_api_path = $this->getStorage()->get('jupyterhub_api_path', '');
        $this->api_token = $this->getStorage()->get('api_token', '');
        $this->default_jupyter_notebook = $this->getStorage()->get('default_jupyter_notebook', '');
    }

    protected function getStorage(): ?ilSetting
    {
        return $this->storage;
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

    public function getProxyUrl(): string
    {
        return $this->proxy_url;
    }

    public function setProxyUrl($proxy_url): void
    {
        $this->proxy_url = $proxy_url;
    }

    public function getJupyterhubServerUrl(): string
    {
        return $this->jupyterhub_server_url;
    }

    public function setJupyterhubServerUrl($jupyterhub_server_url): void
    {
        $this->jupyterhub_server_url = $jupyterhub_server_url;
    }

    public function getJupyterhubApiPath(): string
    {
        return $this->jupyterhub_api_path;
    }

    public function setJupyterhubApiPath(string $jupyterhub_api_path): void
    {
        $this->jupyterhub_api_path = $jupyterhub_api_path;
    }

    public function getApiToken(): string
    {
        return $this->api_token;
    }

    public function setApiToken($api_token): void
    {
        $this->api_token = $api_token;
    }

    public function getDefaultJupyterNotebook(): string
    {
        return $this->default_jupyter_notebook;
    }

    public function setDefaultJupyterNotebook($default_jupyter_notebook): void
    {
        $this->default_jupyter_notebook = $default_jupyter_notebook;
    }

    public function update()
    {
        $this->getStorage()->set('log_level', (string)$this->log_level);
        $this->getStorage()->set('proxy_url', (string)$this->proxy_url);
        $this->getStorage()->set('jupyterhub_server_url', (string)$this->jupyterhub_server_url);
        $this->getStorage()->set('jupyterhub_api_path', (string)$this->jupyterhub_api_path);
        $this->getStorage()->set('api_token', (string)$this->api_token);
        $this->getStorage()->set('default_jupyter_notebook', (string)$this->default_jupyter_notebook);
    }
}