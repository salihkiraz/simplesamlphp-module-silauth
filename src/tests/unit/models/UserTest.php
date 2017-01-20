<?php
namespace Sil\SilAuth\tests\unit\models;

use Sil\SilAuth\auth\Authenticator;
use Sil\SilAuth\models\User;
use Sil\SilAuth\time\UtcTime;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testCalculateBlockUntilUtc()
    {
        // Arrange:
        $testCases = [
            [
                'afterNthFailedLogin' => 0,
                'blockUntilShouldBeNull' => true,
            ], [
                'afterNthFailedLogin' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN - 1,
                'blockUntilShouldBeNull' => true,
            ], [
                'afterNthFailedLogin' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN,
                'blockUntilShouldBeNull' => false,
            ], [
                'afterNthFailedLogin' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN + 1,
                'blockUntilShouldBeNull' => false,
            ],
        ];
        foreach ($testCases as $testCase) {
            
            // Act:
            $actual = User::calculateBlockUntilUtc($testCase['afterNthFailedLogin']);
            
            // Assert:
            $this->assertSame($testCase['blockUntilShouldBeNull'], is_null($actual));
        }
    }
    
    public function testCalculateBlockUntilUtcMaxDelay()
    {
        // Arrange:
        $expected = Authenticator::MAX_SECONDS_TO_BLOCK;
        $nowUtc = new UtcTime();
        
        // Act:
        $blockUntilUtcString = User::calculateBlockUntilUtc(100);
        $blockUntilUtcTime = new UtcTime($blockUntilUtcString);
        $actual = $nowUtc->getSecondsUntil($blockUntilUtcTime);
        
        // Assert:
        $this->assertEquals($expected, $actual, sprintf(
            'Maximum delay should be no more than %s seconds (not %s).',
            $expected,
            $actual
        ), 1);
    }
    
    public function testCalculateSecondsToDelay()
    {
        // Arrange:
        $testCases = [
            ['failedLoginAttempts' => 0, 'expected' => 0],
            ['failedLoginAttempts' => 1, 'expected' => 1],
            ['failedLoginAttempts' => 5, 'expected' => 25],
            ['failedLoginAttempts' => 6, 'expected' => 36],
            ['failedLoginAttempts' => 10, 'expected' => 100],
            ['failedLoginAttempts' => 20, 'expected' => 400],
            ['failedLoginAttempts' => 50, 'expected' => 2500],
            ['failedLoginAttempts' => 60, 'expected' => 3600],
            ['failedLoginAttempts' => 61, 'expected' => 3600],
            ['failedLoginAttempts' => 100, 'expected' => 3600],
        ];
        foreach ($testCases as $testCase) {
            
            // Act:
            $actual = User::calculateSecondsToDelay($testCase['failedLoginAttempts']);
            
            // Assert:
            $this->assertSame($testCase['expected'], $actual, sprintf(
                'Expected %s failed login attempts to result in %s second(s), not %s.',
                var_export($testCase['failedLoginAttempts'], true),
                var_export($testCase['expected'], true),
                var_export($actual, true)
            ));
        }
    }
    
    public function testChangingAnExistingUuid()
    {
        // Arrange:
        $uniqId = uniqid();
        $user = new User();
        $user->attributes = [
            'email' => $uniqId . '@example.com',
            'employee_id' => $uniqId,
            'first_name' => 'Test ' . $uniqId,
            'last_name' => 'User',
            'username' => 'user' . $uniqId,
        ];
        
        // Pre-assert:
        $this->assertTrue($user->save(), sprintf(
            'Failed to create User for test: %s',
            print_r($user->getErrors(), true)
        ));
        $this->assertTrue($user->refresh());
        
        // Act:
        $user->uuid = User::generateUuid();
        
        // Assert:
        $this->assertFalse($user->validate(['uuid']));
    }
    
    public function testGenerateUuid()
    {
        // Arrange: (n/a)
        
        // Act:
        $uuid = User::generateUuid();
        
        // Assert:
        $this->assertNotEmpty($uuid);
    }
    
    public function testGetSecondsUntilUnblocked()
    {
        // Arrange:
        $testCases = [
            ['blockUntilUtc' => null, 'expected' => 0],
            ['blockUntilUtc' => UtcTime::format('+8 seconds'), 'expected' => 8],
            ['blockUntilUtc' => UtcTime::format('+1 minute'), 'expected' => 60],
        ];
        foreach ($testCases as $testCase) {
            $user = new User();
            $user->block_until_utc = $testCase['blockUntilUtc'];
            
            // Act:
            $actual = $user->getSecondsUntilUnblocked();
            
            // Assert:
            $this->assertSame($testCase['expected'], $actual);
        }
    }
    
    public function testIsActive()
    {
        // Arrange:
        $testCases = [
            ['value' => User::ACTIVE_YES, 'expected' => true],
            ['value' => 'YES', 'expected' => true],
            ['value' => 'Yes', 'expected' => true],
            ['value' => 'yes', 'expected' => true],
            ['value' => User::ACTIVE_NO, 'expected' => false],
            ['value' => 'NO', 'expected' => false],
            ['value' => 'No', 'expected' => false],
            ['value' => 'no', 'expected' => false],
            // Handle invalid types/values securely:
            ['value' => true, 'expected' => false],
            ['value' => 1, 'expected' => false],
            ['value' => false, 'expected' => false],
            ['value' => 0, 'expected' => false],
            ['value' => 'other', 'expected' => false],
            ['value' => '', 'expected' => false],
            ['value' => null, 'expected' => false],
        ];
        foreach ($testCases as $testCase) {
            $user = new User();
            $user->active = $testCase['value'];
            
            // Act:
            $actual = $user->isActive();
            
            // Assert:
            $this->assertSame($testCase['expected'], $actual, sprintf(
                'A User with an "active" value of %s should%s be considered active.',
                var_export($user->active, true),
                ($testCase['expected'] ? '' : ' not')
            ));
        }
    }
    
    public function testIsBlockedByRateLimit()
    {
        // Arrange:
        $testCases = [
            [
                'attributes' => [
                    'block_until_utc' => null,
                ],
                'expectedResult' => false,
            ], [
                'attributes' => [
                    'block_until_utc' => UtcTime::format('yesterday'),
                ],
                'expectedResult' => false,
            ], [
                'attributes' => [
                    'block_until_utc' => UtcTime::format('tomorrow'),
                ],
                'expectedResult' => true,
            ],
        ];
        foreach ($testCases as $testCase) {
            $user = new User();
            $user->attributes = $testCase['attributes'];
            
            // Act:
            $actualResult = $user->isBlockedByRateLimit();
            
            // Assert:
            $this->assertSame($testCase['expectedResult'], $actualResult);
        }
    }
    
    public function testIsEnoughFailedLoginsToBlock()
    {
        // Arrange:
        $testCases = [
            ['expected' => false, 'failedLogins' => 0],
            ['expected' => false, 'failedLogins' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN - 1],
            ['expected' => true, 'failedLogins' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN],
            ['expected' => true, 'failedLogins' => Authenticator::BLOCK_AFTER_NTH_FAILED_LOGIN + 1],
        ];
        foreach ($testCases as $testCase) {
            
            // Act:
            $actual = User::isEnoughFailedLoginsToBlock($testCase['failedLogins']);
            
            // Assert:
            $this->assertSame($testCase['expected'], $actual);
        }
    }
    
    public function testIsLocked()
    {
        // Arrange:
        $testCases = [
            ['value' => User::LOCKED_YES, 'expected' => true],
            ['value' => 'YES', 'expected' => true],
            ['value' => 'Yes', 'expected' => true],
            ['value' => 'yes', 'expected' => true],
            ['value' => User::LOCKED_NO, 'expected' => false],
            ['value' => 'NO', 'expected' => false],
            ['value' => 'No', 'expected' => false],
            ['value' => 'no', 'expected' => false],
            // Handle invalid types/values securely:
            ['value' => true, 'expected' => true],
            ['value' => 1, 'expected' => true],
            ['value' => false, 'expected' => true],
            ['value' => 0, 'expected' => true],
            ['value' => 'other', 'expected' => true],
            ['value' => '', 'expected' => true],
            ['value' => null, 'expected' => true],
        ];
        foreach ($testCases as $testCase) {
            $user = new User();
            $user->locked = $testCase['value'];
            
            // Act:
            $actual = $user->isLocked();
            
            // Assert:
            $this->assertSame($testCase['expected'], $actual, sprintf(
                'A User with a "locked" value of %s should%s be considered locked.',
                var_export($user->locked, true),
                ($testCase['expected'] ? '' : ' not')
            ));
        }
    }
    
    public function testRecordLoginAttemptInDatabase()
    {
        // Arrange:
        $uniqId = uniqid();
        $user = new User();
        $user->attributes = [
            'email' => $uniqId . '@example.com',
            'employee_id' => $uniqId,
            'first_name' => 'Test ' . $uniqId,
            'last_name' => 'User',
            'username' => 'user' . $uniqId,
        ];
        
        // Pre-assert:
        $this->assertTrue($user->save(), sprintf(
            'Failed to save User record for test: %s',
            print_r($user->getErrors(), true)
        ));
        $user->refresh();
        $valueBefore = $user->login_attempts;
        $this->assertNotNull($valueBefore);
        
        // Act:
        $user->recordLoginAttemptInDatabase();
        $user->refresh();
        
        // Assert:
        $this->assertSame($valueBefore + 1, $user->login_attempts, sprintf(
            'The value after (%s) was not 1 more than the value before (%s).',
            var_export($user->login_attempts, true),
            var_export($valueBefore, true)
        ));
    }
}
