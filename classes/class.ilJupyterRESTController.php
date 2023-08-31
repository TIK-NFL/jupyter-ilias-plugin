<?php


use exceptions\ilCurlErrorCodeException;

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
//        $this->curl->setOpt(CURLOPT_VERBOSE, 1);
//        $this->curl->setOpt(CURLOPT_TIMEOUT_MS, 2000);
//        $this->curl->setOpt(CURLOPT_HTTPHEADER, array("Accept" => "application/json", "Authorization" => "token secret-token"));
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function execCurlRequest($url, $http_method, $auth_token, $payload = '', $exceptionOnErrorCode = false, $returnHttpCode = false)
    {
        try {
            $this->curl = new ilCurlConnection($url);
            $this->initCurlRequest();

            if ($http_method == 'POST') {
                $this->curl->setOpt(CURLOPT_POST, true);
            } else if ($http_method == 'PUT') {
                $this->curl->setOpt(CURLOPT_CUSTOMREQUEST, $http_method);
            } else if ($http_method == 'HEAD') {
                $this->curl->setOpt(CURLOPT_CUSTOMREQUEST, $http_method);
                $this->curl->setOpt(CURLOPT_NOBODY, true);
            }

            $this->curl->setOpt(CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: token ' . $auth_token));

            if ($payload) {
                $this->curl->setOpt(CURLOPT_POSTFIELDS, $payload);
            }

            $return_body = $this->curl->exec();

            $response_code = (int)$this->curl->getInfo(CURLINFO_HTTP_CODE);
            if ($exceptionOnErrorCode && $response_code > 300) {
                throw new ilCurlErrorCodeException("HTTP server responded with error code " . $response_code . ". (exceptionOnErrorCode set)", $response_code);
            }
            ilLoggerFactory::getLogger('jupyter')->debug('HTTP response code: ' . $response_code);

            if ($returnHttpCode) {
                return $response_code;
            }
            return $return_body;

        } catch (ilCurlConnectionException $exception) {
            ilLoggerFactory::getLogger('jupyter')->debug('HTTP response code: ' . $response_code);
            throw $exception;
        }
    }

    public function initJupyterUser()
    {
        // TODO: Ensure that the user does not already exist. Otherwise, try another username.
        $tmp_user = "u" . time();
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user, 'POST', $this->jupyter_settings->getApiToken());
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user . "/server", 'POST', $this->jupyter_settings->getApiToken());
        $response = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user . "/tokens", 'POST', $this->jupyter_settings->getApiToken());
        $response_json = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $tmp_user_token = $response_json->token;
        return array('user' => $tmp_user, 'token' => $tmp_user_token);
    }

    public function initJupyterNotebook(): array
    {
        // TODO: Ensure that the user does not already exist. Otherwise, try another username.
        $tmp_user = "u" . time();
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user, 'POST', $this->jupyter_settings->getApiToken());
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user . "/server", 'POST', $this->jupyter_settings->getApiToken());
        $response = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/hub/api/users/" . $tmp_user . "/tokens", 'POST', $this->jupyter_settings->getApiToken());
        $response_json = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $tmp_user_token = $response_json->token;
//        $this->tpl->setOnScreenMessage("info", "Token: " . $response_json->token);
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $tmp_user, 'GET', $tmp_user_token);
        $example_json = '{"content":{ "cells": [ { "cell_type": "code", "execution_count": 1, "id": "ae279420", "metadata": {}, "outputs": [ { "name": "stdout", "output_type": "stream", "text": [ "hello world!\n" ] } ], "source": [ "echo \"hello world!\"" ] }, { "cell_type": "code", "execution_count": 0, "id": "c5775578", "metadata": {}, "outputs": [], "source": [] } ], "metadata": { "kernelspec": { "display_name": "Bash", "language": "bash", "name": "bash" }, "language_info": { "codemirror_mode": "shell", "file_extension": ".sh", "mimetype": "text/x-sh", "name": "bash" } }, "nbformat": 4, "nbformat_minor": 5}, "format":"json", "type":"notebook"}';
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $tmp_user . "/api/contents/test.ipynb", 'PUT', $tmp_user_token, $example_json);

        $_SESSION['jupyter_user'] = $tmp_user;
        $_SESSION['jupyter_user_token'] = $tmp_user_token;

        return array('user' => $tmp_user, 'token' => $tmp_user_token);
    }

    public function checkJupyterUser($user, $user_token)
    {
        $response_http_code = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user, 'GET', $user_token, '', false, true);
        return $response_http_code == 200;
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function pullJupyterNotebook($user, $user_token)
    {
        $response = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/test.ipynb", 'GET', $user_token, '', true);
//        $response_json = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
//            $this->tpl->setOnScreenMessage("info", $response);
        return $response;
    }

    public function pushJupyterNotebook($jupyter_notebook_json_str, $user, $user_token)
    {
        $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/test.ipynb", 'PUT', $user_token, $jupyter_notebook_json_str);
    }
}