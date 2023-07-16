<?php

use exceptions\JupyterSessionNotFoundException;

class ilJupyterSession
{
    private ilJupyterRESTController $rest_ctrl;

    private array $user_credentials;

    private string $session_id = '';

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function __construct($session_id=null) {
        $this->rest_ctrl = new ilJupyterRESTController();

        if (!$_SESSION['jupyter_sessions']) {
            $_SESSION['jupyter_sessions'] = array();
        }

        if ($session_id) {
            if ($_SESSION['jupyter_sessions'][$session_id]) {
                $this->user_credentials = $_SESSION['jupyter_sessions'][$session_id];
                $this->session_id = $session_id;
            } else {
                $exception = new JupyterSessionNotFoundException($session_id);
                ilLoggerFactory::getLogger('jupyter')->warning($exception->getMessage());
                throw $exception;
            }
        } else {
            $this->user_credentials = $this->rest_ctrl->initJupyterUser();
            $this->session_id = $this->user_credentials['user'];
            $_SESSION['jupyter_sessions'][$this->session_id] = $this->user_credentials;
        }
    }


    public function destroy() {
        if ($_SESSION['jupyter_sessions'][$this->session_id]) {
            unset($_SESSION['jupyter_sessions'][$this->session_id]);
        } else {
            ilLoggerFactory::getLogger('jupyter')->warning('No jupyter session to destroy.');
        }
    }

    public function isRunning(): bool {
        return isset($this->user_credentials);
    }

    public function getUserCredentials(): array {
        return $this->user_credentials;
    }

    public static function isSessionSet(string $session_id): bool {
        $rest_ctrl = new ilJupyterRESTController();

        if (isset($_SESSION['jupyter_sessions'][$session_id])) {
            $user_credentials = $_SESSION['jupyter_sessions'][$session_id];
            return $rest_ctrl->checkJupyterUser($user_credentials['user'], $user_credentials['token']);
        }
        return false;
    }

    public static function fromCredentials(array $user_credentials) {
        $session_id = $user_credentials['user'];
        if (!self::isSessionSet($session_id)) {
            $_SESSION['jupyter_sessions'][$session_id] = $user_credentials;
            return new ilJupyterSession($session_id);
        } else {
            $exception = new Exception("Could not override running Jupyter session with ID '" . $session_id . "'.");
            ilLoggerFactory::getLogger('jupyter')->error($exception->getMessage());
            throw $exception;
        }
    }

}