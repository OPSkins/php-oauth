# PHP OPSkins OAuth 

Official documentation can be [found here](https://docs.opskins.com/public/en.html#oauth)

This library is split up into 4 classes 
 * `OPSkinsOAuthSettings` Basic settings for interfacing with OPSkins APIs, might want to override the properties in a config of your own. 
 * `OPSkinsOAuth` This is the class that interacts with all the APIs and OAuth.
 * `OPSkinsCurl` Basic class to make curl calls easier
 * `OPSkinsClient` Class which holds all the client variables. 
 
I used file storage for persistent storage. You probably do not want to do this in production as you will run into race conditions. I suggest you override the following methods to use some sort of database like redis,mysql,mongo ect
  
OPSkinsClient 
 * `getClientList()`
 * `storeClient()`
 
OPSkinsOAuth
 * `getAuthUrl()`
 * `verifyReturn()`
  
  
### Example usage

##### Generate auth url and redirect the user to log in on OPSkins.

```php
<?php

require_once 'OPSkinsOAuth.php';

$auth = new OPSkinsOAuth();

# You might want to match up $client->client_id and the users id somewhere so you whos who.
$client = $auth->createOAuthClient();

# This is the URL you will redirect the user to
$redirect_url = $auth->getAuthUrl($client);

header( "Location: $redirect_url" );

```

#### Example of what the return uri would look like

```php
<?php

require_once 'OPSkinsOAuth.php';

$auth = new OPSkinsOAuth();
$client = $auth->verifyReturn($_GET['state'], $_GET['code']);
$auth->getBearerToken($client);

# below is not needed but shows that the user is now authed and you have access to their scopes.
var_dump($auth->testAuthed($client));

```