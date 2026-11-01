# A simple web page in php that monitors the status of server and services

**About This Project:** This is a web page in php that checks HTTP(S) endpoints (in parallel) and raw TCP ports to see if the services are reachable, it also auto-refreshes every 30 seconds and provides a JSON output available at status.php?format=json that can be accessed easily by other programs/websites/apps/discord bots/etc.

**This is released for educational/demonstration purposes, please use with caution and secure this web form if you use it for for your project or discord server.**


# Installation/Use 

To use, Replace the Services and TCP checks with your own urls/ips/ports/etc 
```
['name' => 'Google',           'type' => 'http', 'target' => 'https://www.google.com'],
['name' => 'Microsoft',          'type' => 'http', 'target' => 'https://www.microsoft.com'],
['name' => 'Discord',           'type' => 'http', 'target' => 'https://discord.com'],
['name' => 'Veloren Server',   'type' => 'tcp',  'target' => 'server.veloren.net',    'port' => 14004],
```

and configure the timeout/refresh seconds/useragent, web page style/css, and anything else you'd like to customize.

```
$timeout = 5;          // seconds per check
$refreshSeconds = 30;  // page auto-refresh interval
$userAgent = 'Mozilla/5.0 (compatible; StatusPage/1.0)';
```

Then upload the form to a web host that supports PHP 7.4 (or above preferably) with the cURL extension.

If you wanted to dedicate a subdomain to it, you could even rename it to index.php and upload it to status.yourdomain.example 



## Screenshots

![App Screenshot](https://github.heretic.network/screenshots/web-status-monitor.png)

