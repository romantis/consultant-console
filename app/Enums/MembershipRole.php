<?php

namespace App\Enums;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function isManager(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }
}
