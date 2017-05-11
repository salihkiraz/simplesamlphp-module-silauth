<?php

use Sil\SilAuth\csrf\CsrfProtector;
use Sil\SilAuth\http\Request;
use Sil\SilAuth\log\Psr3SamlLogger;
use Sil\SilAuth\models\FailedLoginUsername;

/**
 * This page shows a username/password login form, and passes information from it
 * to the sspmod_silauth_Auth_Source_SilAuth class
 */

// Retrieve the authentication state
if ( ! array_key_exists('AuthState', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest('Missing AuthState parameter.');
}
$authStateId = $_REQUEST['AuthState'];
$state = SimpleSAML_Auth_State::loadState($authStateId, sspmod_silauth_Auth_Source_SilAuth::STAGEID);

$source = SimpleSAML_Auth_Source::getById($state[sspmod_silauth_Auth_Source_SilAuth::AUTHID]);
if ($source === null) {
    throw new Exception(
        'Could not find authentication source with id '
        . $state[sspmod_silauth_Auth_Source_SilAuth::AUTHID]
    );
}

$errorCode = null;
$errorParams = null;
$username = null;
$password = null;

$csrfProtector = new CsrfProtector(SimpleSAML_Session::getSession());

$globalConfig = SimpleSAML_Configuration::getInstance();
$authSourcesConfig = $globalConfig->getConfig('authsources.php');
$silAuthConfig = $authSourcesConfig->getConfigItem('silauth');

$recaptchaSiteKey = $silAuthConfig->getString('recaptcha.siteKey', null);
$forgotPasswordUrl = $silAuthConfig->getString('link.forgotPassword', null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        $logger = new Psr3SamlLogger();
        $csrfFromRequest = Request::sanitizeInputString(INPUT_POST, 'csrf-token'); 
        if ($csrfProtector->isTokenCorrect($csrfFromRequest)) {
            
            $username = Request::sanitizeInputString(INPUT_POST, 'username');
            $password = Request::sanitizeInputString(INPUT_POST, 'password');
            
            sspmod_silauth_Auth_Source_SilAuth::handleLogin(
                $authStateId,
                $username,
                $password
            );
        } else {
            $logger->error(sprintf(
                'Failed CSRF (user %s).',
                var_export($username, true)
            ));
        }
        
    } catch (SimpleSAML_Error_Error $e) {
        /* Login failed. Extract error code and parameters, to display the error. */
        $errorCode = $e->getErrorCode();
        $errorParams = $e->getParameters();
    }
    
    $csrfProtector->changeMasterToken();
}

$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:loginuserpass.php');
$t->data['stateparams'] = array('AuthState' => $authStateId);
$t->data['username'] = $username;
$t->data['forceUsername'] = false;
$t->data['rememberUsernameEnabled'] = false;
$t->data['rememberMeEnabled'] = false;
$t->data['errorcode'] = $errorCode;
$t->data['errorparams'] = $errorParams;
$t->data['forgotPasswordUrl'] = $forgotPasswordUrl;
$t->data['csrfToken'] = $csrfProtector->getMasterToken();
if ( ! empty($username)) {
    if (FailedLoginUsername::isCaptchaRequiredFor($username)) {
        $t->data['recaptcha.siteKey'] = $recaptchaSiteKey;
    }
}

if (isset($state['SPMetadata'])) {
    $t->data['SPMetadata'] = $state['SPMetadata'];
} else {
    $t->data['SPMetadata'] = null;
}

$t->show();
exit();
