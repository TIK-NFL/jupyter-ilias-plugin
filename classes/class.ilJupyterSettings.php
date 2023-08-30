<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Global Jupyter settings
 */
class ilJupyterSettings
{
    private static $instance = null;

    /**
     *
     * @var ilSetting
     */
    private $storage = null;

    private $log_level;

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
     * Update settings
     */
    public function update()
    {
        $this->getStorage()->set('log_level', $this->getLogLevel());
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
        $this->setLogLevel($this->getStorage()->get('log_level', $this->log_level));
    }
}