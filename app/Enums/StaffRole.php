<?php

namespace App\Enums;

enum StaffRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case OPERATOR = 'operator';

    public static function fromInternalMembership(MembershipRole $role): ?self
    {
        return match ($role) {
            MembershipRole::OWNER => self::OWNER,
            MembershipRole::ADMIN => self::ADMIN,
            MembershipRole::MEMBER => self::OPERATOR,
            default => null,
        };
    }

    public function canOperateDelivery(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canGovernInternal(): bool
    {
        return $this === self::OWNER;
    }

    public function canImpersonate(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }
}
