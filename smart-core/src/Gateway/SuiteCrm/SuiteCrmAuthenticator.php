<?php

namespace SmartCore\Gateway\SuiteCrm;

use BeanFactory;
use SmartCore\Gateway\Authenticator;
use User;

/**
 * Checks a username and password against the CRM's own active users.
 */
final class SuiteCrmAuthenticator implements Authenticator
{
    public function attempt(string $username, string $password): ?string
    {
        if ($username === '' || $password === '') {
            return null;
        }

        $user = BeanFactory::newBean('Users');
        $user->retrieve_by_string_fields(['user_name' => $username, 'status' => 'Active']);

        if (empty($user->id) || ! empty($user->deleted) || ! User::checkPassword($password, $user->user_hash)) {
            return null;
        }

        return $user->id;
    }

    /** Sets the CRM's current user, so records get the right created-by and assigned user. */
    public static function actAs(string $userId): void
    {
        $user = BeanFactory::getBean('Users', $userId);

        if ($user && ! empty($user->id)) {
            $GLOBALS['current_user'] = $user;
        }
    }
}
