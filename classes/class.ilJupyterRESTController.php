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

            $this->curl->setOpt(CURLOPT_HTTPHEADER, array('Accept: application/json', 'Authorization: token ' . $auth_token));
            $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);

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
        $max_tries = 3;

        $tmp_user = "";
        $tmp_user_token = "";
        $user_path = '';

        $root_path = $this->jupyter_settings->getJupyterhubServerUrl() . $this->jupyter_settings->getJupyterhubApiPath();
        $http_response_code = $this->execCurlRequest($root_path, 'GET', $this->jupyter_settings->getApiToken(), '', false, true, false);

        if ($http_response_code != 200) {
            throw new JupyterUnreachableServerException("Failed to call the jupyter REST API at " . $root_path);
        }

        while (!$created && $increment < $max_tries) {
            $tmp_user = "u" . $microtime . '_' . $random_num . '_' . $increment;
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


    public function checkJupyterUser($user): bool
    {
        $response_http_code = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . $this->jupyter_settings->getJupyterhubApiPath() . "/users/" . $user, 'GET', $this->jupyter_settings->getApiToken(), '', false, true, false);
        return $response_http_code == 200;
    }

    public function checkJupyterUserServer($user, $user_token): bool
    {
        $jupyter_api_status_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/status";
        $response_http_code = $this->execCurlRequest($jupyter_api_status_url, 'GET', $user_token, '', false, true, false);
        return $response_http_code == 200;
    }

    public function checkJupyterUserAndServer($user, $user_token): bool
    {
        return $this->checkJupyterUser($user) && $this->checkJupyterUserServer($user, $user_token);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function deleteJupyterUser($user, $user_token): bool
    {
        $response_http_code = $this->execCurlRequest($this->jupyter_settings->getJupyterhubServerUrl() . $this->jupyter_settings->getJupyterhubApiPath() . "/users/" . $user, 'DELETE', $user_token, '', false, true, false);

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
    public function pullJupyterProjectMetaData($user, $user_token): array
    {
        // TODO: $jupyter_notebook_url uses a single notebook to infer meta data about the project.
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
        $jupyter_user_url = $this->jupyter_settings->getJupyterhubServerUrl() . $this->jupyter_settings->getJupyterhubApiPath() . "/users/" . $user;
        $response = $this->execCurlRequest($jupyter_user_url, 'GET', $user_token, '', true, true);
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
        $users_path = $this->jupyter_settings->getJupyterhubServerUrl() . $this->jupyter_settings->getJupyterhubApiPath() . "/users";
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
        $this->execCurlRequest($jupyter_notebook_url, 'PUT', $user_token, $jupyter_notebook_json_str, true);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function requestDirectoryContent($user, $user_token, $directory_path = ''): array
    {
        $jupyter_contents_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/" . $directory_path;
        $response = $this->execCurlRequest($jupyter_contents_url, 'GET', $user_token, '', true);
        $root = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        $content_paths = array();
        $content_types = array();
        foreach ($root->content as $jupyter_object) {
            $content_paths[] = $jupyter_object->path;
            $content_types[] = $jupyter_object->type;
        }
        return array('paths' => $content_paths, 'types' => $content_types);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pullJupyterPaths($user, $user_token): array
    {
        $directory_content = $this->requestDirectoryContent($user, $user_token);
        $content_paths = $directory_content['paths'];
        $content_types = $directory_content['types'];
        $path_list = array();
        $type_list = array();

        $num_paths = count($content_paths);
        for ($i = 0; $i < $num_paths; $i++) {
            $path_list[] = $content_paths[$i];
            $type_list[] = $content_types[$i];

            if ($content_types[$i] == 'directory') {
                $new_directory_content = $this->requestDirectoryContent($user, $user_token, $content_paths[$i]);
                array_push($content_paths, ...$new_directory_content['paths']);
                array_push($content_types, ...$new_directory_content['types']);
                $num_paths += count($new_directory_content['paths']);
            }
        }

        return array('paths' => $path_list, 'types' => $type_list);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     */
    public function pullJupyterContentObject($user, $user_token, $path): string
    {
        $jupyter_content_object_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/" . $path;
        ilLoggerFactory::getLogger('jupyter')->debug("Pulling remote jupyter content object from " . $jupyter_content_object_url);
        return $this->execCurlRequest($jupyter_content_object_url, 'GET', $user_token, '', true);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pullJupyterProject($user, $user_token): string
    {
        ilLoggerFactory::getLogger('jupyter')->info("Pulling remote jupyter project from user server " . $user);
        $paths = $this->pullJupyterPaths($user, $user_token);
        $path_list = $paths['paths'];
        $type_list = $paths['types'];
        $json_object = json_decode('{"jupyter_project": []}', false, 512, JSON_THROW_ON_ERROR);
        for ($i = 0; $i < count($path_list); $i++) {
            $path = $path_list[$i];
            $type = $type_list[$i];
            $req_path = $path . (($type == 'directory') ? '?content=0' : '');
            $content_object = $this->pullJupyterContentObject($user, $user_token, $req_path);
            $json_object->jupyter_project[] = json_decode($content_object, false, 512, JSON_THROW_ON_ERROR);
        }
        return json_encode($json_object);
    }

    /**
     * @throws ilCurlConnectionException
     * @throws ilCurlErrorCodeException
     * @throws JsonException
     */
    public function pushJupyterProject($jupyter_project_json_str, $user, $user_token)
    {
        ilLoggerFactory::getLogger('jupyter')->info("Pushing local jupyter project to user server " . $user);
        $root = json_decode($jupyter_project_json_str, false, 512, JSON_THROW_ON_ERROR);
        $created_paths = array();

        foreach ($root->jupyter_project as $jupyter_object) {
            $dirname = dirname($jupyter_object->path);

            // Create underlying directories if required. Since all jupyter files including directories are stored
            // iteratively and level-wise, this case might only occur on manually reordered jupyter JSON elements.
            if ($dirname != '.') {
                $path_dirs = explode(DIRECTORY_SEPARATOR, $dirname);
                $current_path = '';
                foreach ($path_dirs as $dir) {
                    $current_path .= (empty($current_path) ? '' : '/') . $dir;
                    if (!in_array($current_path, $created_paths)) {
                        $path_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/" . $current_path;
                        $this->execCurlRequest($path_url, 'PUT', $user_token, '{"type": "directory"}', true);
                        $created_paths[] = $current_path;
                    }
                }
            }

            // Create the file or leaf directory if not already created above.
            if (!in_array($jupyter_object->path, $created_paths)) {
                $path_url = $this->jupyter_settings->getJupyterhubServerUrl() . "/user/" . $user . "/api/contents/" . $jupyter_object->path;
                $this->execCurlRequest($path_url, 'PUT', $user_token, json_encode($jupyter_object), true);
                $created_paths[] = $jupyter_object->path;
            }
        }
    }
}