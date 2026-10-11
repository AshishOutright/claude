<?php

namespace SmartCore\Gateway;

interface Authenticator
{
    /** Returns the user id when the username and password are right. */
    public function attempt(string $username, string $password): ?string;
}
