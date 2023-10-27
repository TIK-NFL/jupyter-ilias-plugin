<?php


use exceptions\ilCurlErrorCodeException;
use exceptions\JupyterSessionException;
use exceptions\JupyterUnreachableServerException;

class ilJupyterRESTController
{
    private $curl = null;
    private ilJupyterSettings $jupyter_settings;


    public function __construct()
    {
        $this->jupyter_settings = ilJupyterSettings::getInstance();
    }

    /**
     * @throws ilCurlConnectionException
     */
    private function initCurlRequest()
    {
        $this->curl->init();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $this->curl->setOpt(CURLOPT_RETURNTRANSFER, 1);
//        $this->curl->setOpt(CURLOPT_HEADER, 1);
//        $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, 1);
//        $this->curl->setOpt(CURLOPT_VERBOSE, 1);
//        $this->curl->setOpt(CURLOPT_TIMEOUT_MS, 2000);
//        $this->curl->setOpt(CURLOPT_HTTPHEADER, array("Accept" => "application/json", "Authorization" => "token secret-token"));
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function execCurlRequest($url, $http_method, $auth_token = '', $payload = '', $exceptionOnErrorCode = false, $returnHttpCode = false, $returnHttpBody = true)
    {
        try {
            $this->curl = new ilCurlConnection($url);
            $this->initCurlRequest();

            if ($http_method == 'POST') {
                $this->curl->setOpt(CURLOPT_POST, true);
            } else if ($http_method == 'PUT') {
                $this->curl->setOpt(CURLOPT_CUSTOMREQUEST, $http_method);
            } else if ($http_method == 'DELETE') {
                $this->curl->setOpt(CURLOPT_CUSTOMREQUEST, $http_method);
            } else if ($http_method == 'HEAD') {
                $this->curl->setOpt(CURLOPT_CUSTOMREQUEST, $http_method);
                $this->curl->setOpt(CURLOPT_NOBODY, true);
            }

            $this->curl->setOpt(CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: Bearer ' . $auth_token));

            if ($payload) {
                $this->curl->setOpt(CURLOPT_POSTFIELDS, $payload);
            }

            $response_body = $this->curl->exec();
            $response_code = (int)$this->curl->getInfo(CURLINFO_HTTP_CODE);

            if ($exceptionOnErrorCode && $response_code >= 400) {
                throw new ilCurlErrorCodeException("HTTP server responded with error code " . $response_code . ". (exceptionOnErrorCode set)", $response_code);
            }
            ilLoggerFactory::getLogger('jupyter')->debug('HTTP response code: ' . $response_code);

            if ($returnHttpCode && $returnHttpBody) {
                return array(
                    'response_code' => $response_code,
                    'response_body' => $response_body
                );
            } else if ($returnHttpCode && !$returnHttpBody) {
                return $response_code;
            }
            return $response_body;

        } catch (ilCurlConnectionException $exception) {
            ilLoggerFactory::getLogger('jupyter')->debug('HTTP response code: ' . $response_code);
            throw $exception;
        }
    }

    /**
     * @throws ilCurlConnectionException
     * @throws JupyterSessionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     * @throws JupyterUnreachableServerException
     */
    public function initJupyterUser(): array
    {
        $microtime = floor(microtime(true) * 1000);
        $random_num = str_pad(rand(1, 10**(10 - 1)), 10, '0', STR_PAD_LEFT);
        $login = $GLOBALS['ilias']->account->getLogin();
        $increment = 0;

        $created = false;
        $max_tries = 3;  // TODO: extract attribute/property

        $tmp_user = "";
        $tmp_user_token = "";
        $user_path = '';

        $root_path = $this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api";
        $http_response_code = $this->execCurlRequest($root_path, 'GET', $this->jupyter_settings->getApiToken(), '', false, true, false);

        if ($http_response_code != 200) {
            throw new JupyterUnreachableServerException("Failed to call the jupyter REST API at " . $root_path);
        }

        while (!$created && $increment < $max_tries) {
            $tmp_user = "u" . $microtime . '.' . $random_num . '.' . $increment;
            $user_path = $root_path . "/users/" . $tmp_user;
            $http_response_code = $this->execCurlRequest($user_path, 'GET', $this->jupyter_settings->getApiToken(), '', false, true, false);

            if ($http_response_code == 404) {
                ilLoggerFactory::getLogger('jupyter')->info("Creating temporary user '" . $tmp_user ."' for ILIAS account login '" . $login . "' at '" . $user_path . "'.");

                $this->execCurlRequest($user_path, 'POST', $this->jupyter_settings->getApiToken());
                $this->execCurlRequest($user_path . "/server", 'POST', $this->jupyter_settings->getApiToken());
                $response = $this->execCurlRequest($user_path . "/tokens", 'POST', $this->jupyter_settings->getApiToken());
                $response_json = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
                $tmp_user_token = $response_json->token;

                $http_code_user_created = $this->execCurlRequest($user_path, 'GET', $this->jupyter_settings->getApiToken(), '', false, true, false);
                $created = ($http_code_user_created == 200);
            }
            $increment++;
        }

        if (!$created) {
            if ($increment == $max_tries) {
                throw new JupyterSessionException("Maximum number of user creation tries exceeded.");
            }
            throw new JupyterSessionException("Failed to create temporary user at '" . $user_path . "'.");
        }

        return array('user' => $tmp_user, 'token' => $tmp_user_token);
    }


    public function checkJupyterUser($user, $user_token): bool
    {
        $response_http_code = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user, 'GET', $user_token, '', false, true, false);
        return $response_http_code == 200 || $response_http_code == 302;
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function deleteJupyterUser($user, $user_token): bool
    {
        $response_http_code = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $user, 'DELETE', $user_token, '', false, true, false);

        $deleted = $response_http_code == 204;
        if ($deleted) {
            ilLoggerFactory::getLogger('jupyter')->info("Deleted temporary user '" . $user ."'.");
        } else {
            ilLoggerFactory::getLogger('jupyter')->info("Failed to clean up temporary user '" . $user ."'.");
        }
        return $deleted;
    }


    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pullJupyterNotebookMetaData($user, $user_token): array
    {
        $jupyter_notebook_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/default.ipynb";
        $response = $this->execCurlRequest($jupyter_notebook_url, 'GET', $user_token, '', true, true);
        if ($response['response_code'] == 200) {
            $response_json = json_decode($response['response_body'], true, 512, JSON_THROW_ON_ERROR);
            return array(
                "created" => date('U', strtotime($response_json['created'])),
                "last_modified" => date('U', strtotime($response_json['last_modified'])),
            );
        }
        return array();
    }

    public function pullJupyterUserMetaData($user, $user_token): array
    {
        $jupyter_notebook_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $user;
        $response = $this->execCurlRequest($jupyter_notebook_url, 'GET', $user_token, '', true, true);
        $response_json = json_decode($response['response_body'], true, 512, JSON_THROW_ON_ERROR);
        if ($response['response_code'] == 200) {
            return array(
                "created" => date('U', strtotime($response_json['created'])),
                "last_activity" => date('U', strtotime($response_json['last_activity'])),
            );
        }
        return array();
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pullJupyterUsers()
    {
        $users_path = $this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users";
        $response = $this->execCurlRequest($users_path, 'GET', $this->jupyter_settings->getApiToken());
        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function pullJupyterNotebook($user, $user_token)
    {
        $jupyter_notebook_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/default.ipynb";
        ilLoggerFactory::getLogger('jupyter')->debug("Pulling remote jupyter notebook from " . $jupyter_notebook_url);
        return $this->execCurlRequest($jupyter_notebook_url, 'GET', $user_token, '', true);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function pushJupyterNotebook($jupyter_notebook_json_str, $user, $user_token)
    {
        $jupyter_notebook_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/default.ipynb";
        ilLoggerFactory::getLogger('jupyter')->debug("Pushing local jupyter notebook '" . $jupyter_notebook_json_str . "' to " . $jupyter_notebook_url);
        $this->execCurlRequest($jupyter_notebook_url, 'PUT', $user_token, $jupyter_notebook_json_str);
    }
}