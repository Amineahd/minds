<?php

/**
 * Elgg OAuth2 server implementation
 *
 * Uses the oauth2-server-php library by Brent Shaffer
 * https://github.com/bshaffer/oauth2-server-php
 * 
 * @author Billy Gunn (billy@arckinteractive.com)
 * @copyright Minds.com 2013
 * @link http://minds.com
 */

elgg_register_event_handler('init','system','oauth2_init');

function oauth2_init() {

    $base = elgg_get_plugins_path() . 'oauth2';

    // Register our OAuth2 storage implementation
    elgg_register_class('ElggOAuth2DataStore', "$base/lib/ElggOAuth2DataStore.php");

    // Register our oauth2 library
    elgg_register_library('oauth2', "$base/lib/oauth2.php");

    // page handler
    elgg_register_page_handler('oauth2', 'oauth2_page_handler');

    // Register actions
    elgg_register_action('oauth2/register', $base . '/actions/register.php');
    elgg_register_action('oauth2/unregister', $base . '/actions/unregister.php');
    elgg_register_action('oauth2/delete', $base . '/actions/delete.php');
    elgg_register_action('oauth2/authorize', $base . '/actions/authorize.php');

    // Hook into pam
    register_pam_handler('oauth2_pam_handler', 'sufficient', 'user');
    register_pam_handler('oauth2_pam_handler', 'sufficient', 'api');

    // register javascript
    $js = elgg_get_simplecache_url('js', 'oauth2/oauth2');
    elgg_register_simplecache_view('js/oauth2/oauth2');
    elgg_register_js('oauth2', $js, 'footer');
			
	// Add the subtypes
	run_function_once('oauth_run_once');
}

function oauth2_page_handler($page) {

    // Load our library methods
    elgg_load_library('oauth2');

    // Load the javascript
    elgg_load_js('oauth2');

    $base = elgg_get_plugins_path() . 'oauth2';

    $pages = $base . '/pages/oauth2';

    switch ($page[0]) {

        case 'authorize':
            require $pages . "/authorize.php";
            break;

        case 'grant':
            oauth2_grant();
            break;

        case 'get_user':
            oauth2_get_user_by_access_token();
            break;

        case 'regenerate':
            echo oauth2_generate_client_secret();
            break;

        case 'add':
        case 'register':
            require $pages . "/register.php";
            break;

        case 'applications':
            require $pages . "/applications.php";
            break;

    }

    return true;
}

/**
 * PAM: Confirm that the call includes a access token
 *
 * @return bool
 */
function oauth2_pam_handler($credentials = NULL) {

    // Load our oauth2 library
    elgg_load_library('oauth2');

    // Get our custom storage object
    $storage = new ElggOAuth2DataStore();

    // Create a server instance
    $server = new OAuth2_Server($storage);

    // Validate the request
    if (!$server->verifyAccessRequest(OAuth2_Request::createFromGlobals())) {
        return false;
    }

    // Get the token data
    $token = $storage->getAccessToken(get_input('access_token'));

    // get the user associated with this token
    $user = get_entity($token['user_id']);

    // couldn't get the user
    if (!$user || !($user instanceof ElggUser)) {
        return false;
    }

    // try logging in the user object here
    if (!login($user)) {
        return false;
    }

    // save the fact that we've validated this request already

    // tell the PAM system that it worked
    return true;

}

/*
 * Run once method to register subtypes
 *
 */
function oauth2_run_once() {
	add_subtype('object', 'oauth2_client');
	add_subtype('object', 'oauth2_access_token');
	add_subtype('object', 'oauth2_refresh_token');
	add_subtype('object', 'oauth2_auth_code');
}

  

