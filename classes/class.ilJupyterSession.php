<?php

use exceptions\JupyterSessionNotFoundException;
use exceptions\JupyterSessionException;
use exceptions\ilCurlErrorCodeException;
use exceptions\JupyterUnreachableServerException;


class ilJupyterSession
{
    private ilJupyterRESTController $rest_ctrl;
    private ilJupyterDBController $db_ctrl;

    private array $user_credentials;

    private string $session_id = '';

    /**
     * @throws JupyterSessionException
     * @throws JupyterSessionNotFoundException
     * @throws ilCurlErrorCodeException
     * @throws ilCurlConnectionException
     * @throws JupyterUnreachableServerException
     * @throws JsonException
     */
    public function __construct($session_id = null)
    {
        $this->rest_ctrl = new ilJupyterRESTController();
        $this->db_ctrl = new ilJupyterDBController();

        if (!$_SESSION['jupyter_sessions']) {
            $_SESSION['jupyter_sessions'] = array();
        }

        if ($session_id) {
            if ($_SESSION['jupyter_sessions'][$session_id]) {
                $this->user_credentials = $_SESSION['jupyter_sessions'][$session_id];
                $this->session_id = $session_id;
                ilLoggerFactory::getLogger('jupyter')->debug("Reusing existent jupyter session with ID '" . $session_id . "'.");
            } else {
                $exception = new JupyterSessionNotFoundException($session_id);
                ilLoggerFactory::getLogger('jupyter')->warning($exception->getMessage());
                throw $exception;
            }
        } else {
            $this->user_credentials = $this->rest_ctrl->initJupyterUser();
            $this->db_ctrl->addTemporarySessionRecord($this->user_credentials);
            $this->session_id = $this->user_credentials['user'];
            $_SESSION['jupyter_sessions'][$this->session_id] = $this->user_credentials;
        }
    }


    public function destroy()
    {
        if ($_SESSION['jupyter_sessions'][$this->session_id]) {
            ilLoggerFactory::getLogger('jupyter')->debug("Destroying jupyter session with ID '" . $this->session_id . "'.");
            unset($_SESSION['jupyter_sessions'][$this->session_id]);
        } else {
            ilLoggerFactory::getLogger('jupyter')->warning('No jupyter session to destroy.');
        }
    }

    public function isRunning(): bool
    {
        return isset($this->user_credentials);
    }

    public function getUserCredentials(): array
    {
        return $this->user_credentials;
    }

    public static function isSessionSet(string $session_id): bool
    {
        $rest_ctrl = new ilJupyterRESTController();

        if (isset($_SESSION['jupyter_sessions'][$session_id])) {
            $user_credentials = $_SESSION['jupyter_sessions'][$session_id];
            return $rest_ctrl->checkJupyterUser($user_credentials['user'], $user_credentials['token']);
        }
        return false;
    }

    /**
     * @throws JupyterSessionException
     * @throws ilCurlConnectionException
     * @throws JupyterSessionNotFoundException
     * @throws ilCurlErrorCodeException
     * @throws JupyterUnreachableServerException
     * @throws JsonException
     */
    public static function fromCredentials(array $user_credentials): ilJupyterSession
    {
        $session_id = $user_credentials['user'];
        if (!self::isSessionSet($session_id)) {
            $_SESSION['jupyter_sessions'][$session_id] = $user_credentials;
            ilLoggerFactory::getLogger('jupyter')->debug("Obtaining a new jupyter session from credentials for user '" . $session_id . "'.");
            return new ilJupyterSession($session_id);
        } else {
            $exception = new JupyterSessionException("Could not override running Jupyter session with ID '" . $session_id . "'.");
            ilLoggerFactory::getLogger('jupyter')->error($exception->getMessage());
            throw $exception;
        }
    }

}