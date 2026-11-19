<?php

namespace App\Enums;

enum StakeholderRole: string
{
    case VIEWER = 'viewer';
    case MEMBER = 'member';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::VIEWER => 'Viewer (read only)',
            self::MEMBER => 'Member (can submit forms)',
            self::ADMIN => 'Admin (manage engagement)',
        };
    }
}
