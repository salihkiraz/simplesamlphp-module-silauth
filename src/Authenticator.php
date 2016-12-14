<?php
namespace Sil\SilAuth;

use Sil\SilAuth\models\User;

class Authenticator
{
    private $errors = [];
    
    /**
     * Attempt to authenticate using the given username and password. Check
     * isAuthenticated() to see whether authentication was successful.
     * 
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        if (empty($username)) {
            $this->addError('Please provide a username');
            return;
        }
        
        if (empty($password)) {
            $this->addError('Please provide a password');
            return;
        }
        
        /* @var $user User */
        $user = User::findByUsername($username) ?? (new User());
        
        if ($user->isBlockedByRateLimit()) {
            $this->addError(
                'There have been too many failed logins for this account. Please wait awhile, then try again.'
            );
            return;
        }
        
        if ( ! $user->isActive()) {
            $this->addError(
                "That account is not active. If it is your account, please contact your organization's help desk."
            );
            return;
        }
        
        if ($user->isLocked()) {
            $this->addError(
                "That account is locked. If it is your account, please contact your organization's help desk."
            );
            return;
        }
        
        /* Check the given password even if we have no such user, to avoid
         * exposing the existence of certain users through a timing attack.  */
        $passwordHash = (($user === null) ? null : $user->password_hash);
        if ( ! password_verify($password, $passwordHash)) {
            if ( ! $user->isNewRecord) {
                $user->recordLoginAttemptInDatabase();
            }
            $this->addError('Either the username or the password was not correct. Please try again.');
            return;
        }
        
        $user->resetFailedLoginAttemptsInDatabase();
        
        // NOTE: If we reach this point, the user successfully authenticated.
    }
    
    protected function addError($errorMessage)
    {
        $this->errors[] = $errorMessage;
    }
    
    /**
     * Get any error messages.
     * 
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    protected function hasErrors()
    {
        return (count($this->errors) > 0);
    }
    
    /**
     * Check whether authentication was successful. If not, call getErrors() to
     * find out why not.
     * 
     * @return bool
     */
    public function isAuthenticated()
    {
        return ( ! $this->hasErrors());
    }
}
