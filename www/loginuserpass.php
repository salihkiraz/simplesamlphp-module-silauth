<?php

/**
 * This page shows a username/password login form, and passes information from it
 * to the sspmod_silauth_Auth_SilAuth class
 */

// Retrieve the authentication state
if (!array_key_exists('AuthState', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest('Missing AuthState parameter.');
}
$authStateId = $_REQUEST['AuthState'];
$state = SimpleSAML_Auth_State::loadState($authStateId, sspmod_silauth_Auth_Source_SilAuth::STAGEID);

$source = SimpleSAML_Auth_Source::getById($state[sspmod_silauth_Auth_Source_SilAuth::AUTHID]);
if ($source === null) {
    throw new Exception('Could not find authentication source with id ' . $state[sspmod_silauth_Auth_Source_SilAuth::AUTHID]);
}

$errorCode = null;
$errorParams = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        // Either username or password set - attempt to log in
        if (empty($_POST['username'])) {
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        } elseif (empty($_POST['password'])) {
            throw new SimpleSAML_Error_Error('WRONGUSERPASS');
        }

        sspmod_silauth_Auth_Source_SilAuth::handleLogin($authStateId, $username, $password);
    } catch (SimpleSAML_Error_Error $e) {
        /* Login failed. Extract error code and parameters, to display the error. */
        $errorCode = $e->getErrorCode();
        $errorParams = $e->getParameters();
    }
}

$globalConfig = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:loginuserpass.php');
$t->data['stateparams'] = array('AuthState' => $authStateId);
$t->data['username'] = $username;
$t->data['errorcode'] = $errorCode;
$t->data['errorparams'] = $errorParams;

if (isset($state['SPMetadata'])) {
    $t->data['SPMetadata'] = $state['SPMetadata'];
} else {
    $t->data['SPMetadata'] = null;
}

$t->show();
exit();

