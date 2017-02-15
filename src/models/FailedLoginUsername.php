<?php
namespace Sil\SilAuth\models;

use Psr\Log\LoggerAwareInterface;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use Yii;

class FailedLoginUsername extends FailedLoginUsernameBase implements LoggerAwareInterface
{
    use \Sil\SilAuth\traits\LoggerAwareTrait;
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'occurred_at_utc' => Yii::t('app', 'Occurred At (UTC)'),
        ]);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => CreatedAtUtcBehavior::className(),
                'attributes' => [
                    Model::EVENT_BEFORE_VALIDATE => 'occurred_at_utc',
                ],
            ],
        ];
    }
    
    /**
     * Find the records with the given username (if any).
     * 
     * @param string $username The username.
     * @return FailedLoginUsername[] An array of any matching records.
     */
    public static function findAllByUsername($username)
    {
        return self::findAll(['username' => $username]);
    }
    
    /**
     * Get the number of seconds remaining until the block_until_utc datetime is
     * reached. Returns zero if the user is not blocked.
     * 
     * @return int
     */
    public function getSecondsUntilUnblocked()
    {
        if ($this->block_until_utc === null) {
            return 0;
        }
        
        $nowUtc = new UtcTime();
        $blockUntilUtc = new UtcTime($this->block_until_utc);
        $remainingSeconds = $nowUtc->getSecondsUntil($blockUntilUtc);
        
        return max($remainingSeconds, 0);
    }
    
    /**
     * Get a human-friendly wait time.
     *
     * @return WaitTime
     */
    public function getWaitTimeUntilUnblocked()
    {
        $secondsUntilUnblocked = $this->getSecondsUntilUnblocked();
        return new WaitTime($secondsUntilUnblocked);
    }
    
    public function isBlockedByRateLimit()
    {
        return ($this->getSecondsUntilUnblocked() > 0);
    }
    
    public static function isCaptchaRequiredFor($username)
    {
        throw new \Exception(__CLASS__ . '.' . __FUNCTION__ . ' not yet implemented.');
        
        //return ($this->login_attempts >= Authenticator::REQUIRE_CAPTCHA_AFTER_NTH_FAILED_LOGIN);
    }
    
}
