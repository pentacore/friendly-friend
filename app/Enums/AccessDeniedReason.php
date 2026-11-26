<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why someone was turned away. The value is flashed as `error` to the login
 * page, which shows a matching message.
 */
enum AccessDeniedReason: string
{
    case NotMember = 'not-member';
    case MissingRole = 'missing-role';
    case AccessRevoked = 'access-revoked';
    case OauthFailed = 'oauth-failed';
}
