## Jupyter plugin for ILIAS

This plugin introduces a new question type which enables for testing and assessing jupyter notebooks within the ILIAS platform.
It incorporates the communication to arbitrary jupyterhub servers executing the jupyter notebooks, internal database operations and test and assessment features like scoring, feedbacks and hints.
Note that jupyterhub as well as the ILIAS webserver needs to be configured too for plugin's normal operation.

### Preparation and Install

#### ILIAS webserver preparation
__Important__: This plugin sends requests to Jupyterhub URLs that do not match with the origin.
To avoid conflicts with the CORS policies, a `ProxyPass` must be specified on the web server which hosts ILIAS or on the load balancer (e.g., nginx) which routes requests to the ILIAS web server.
The local path `/jupyter` must be mapped to the URL of jupyterhub for request as well as for response headers.

The following example shows a ProxyPass to jupyterhub for HTTPS connections in apache webservers.
If needed, adjust the ProxyPass target host and port `jupyterhub_proxy:8000` accordingly.

```apacheconf
<VirtualHost _default_:443>
    DocumentRoot /var/www/html

    #
    # SSL config
    #

    SSLEngine on
    SSLCertificateFile	/etc/ssl/certs/server.crt
    SSLCertificateKeyFile /etc/ssl/private/server.key

    #
    # SSL reverse proxy
    #

    SSLProxyEngine On

    # TODO: Disabled for development. Be sure to remove/enable in production environments.
    SSLProxyVerify none
    SSLProxyCheckPeerName off

    # Rewrite rules to proxy websocket connections
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule /jupyter/(.*) "wss://jupyterhub_proxy:8000/jupyter/$1" [P,L]

    <Location "/jupyter">
        # preserve host header to avoid cross-origin problems
        ProxyPreserveHost on
        ProxyPass         https://jupyterhub_proxy:8000/jupyter
        ProxyPassReverse  https://jupyterhub_proxy:8000/jupyter
        RequestHeader     set "X-Forwarded-Proto" expr=%{REQUEST_SCHEME}
    </Location>

</VirtualHost>
```

Enable the following apache modules required by the configuration above and restart the webserver:
```
a2enmod ssl rewrite proxy headers proxy_http proxy_wstunnel
systemctl restart apache.service
```

### Install
1. Access the installation directory of your running ILIAS instance (e.g.,  `/var/www/ilias`) and clone the jupyter plugin:
    ```
    cd /var/www/ilias
    git clone https://github.com/TIK-NFL/Jupyter.git ./Customizing/global/plugins/Modules/TestQuestionPool/Questions/assJupyter
    ```
2. Access ILIAS by a web browser and go to:  **Administration  →  Extending ILIAS  →  Plugins**.
3. Locate the jupyter plugin and install it by clicking **Actions → Install**.
4. Finally, activate the jupyter plugin by clicking **Actions → Activate**.

### Configuration
1. Access ILIAS and go to  **Administration  →  Extending ILIAS  →  Plugins**.
2. On the  assJupyter  entry, click:  **Actions  →  Configure**.
3. Refer to the following table:
    
|                        Property | Description                                                        | Example value                                       |
|--------------------------------:|--------------------------------------------------------------------|-----------------------------------------------------|
|                       Log-Level | Webserver logging level                                            | DEBUG                                               |
|                       Proxy URL | URL to the local ILIAS webserver providing the ProxyPass           | `https://127.0.0.1/jupyter`                         |
|           Jupyterhub server URL | URL to the Jupyterhub installation including the REST API server   | `https://jupyterhub.example-university.edu/jupyter` |
|                       API token | API token for the REST server access                               | `my-api-token`                                      |
| Default Jupyter Notebook (JSON) | JSON of the initial jupyter notebook (keep as general as possible) | (see below)                                         |

- Example for default jupyter notebook (JSON)
   ```
   {
     "content": {
       "cells": [ ],
       "metadata": {
         "kernelspec": {
           "display_name": "Python 3 (ipykernel)",
           "language": "python",
           "name": "python3"
         }
       },
       "nbformat": 4,
       "nbformat_minor": 5
     },
     "format": "json",
     "type": "notebook"
   }
   ```
4. **Save** and optionally **test** the configuration. Note that the configuration test requires changes to be saved before.

#### Integration (optional)
- Activate the manual scoring for jupyter questions in **Administration → Repository and Objects → Test and Assessment**.