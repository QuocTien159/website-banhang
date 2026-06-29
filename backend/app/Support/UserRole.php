<?php

namespace App\Support;

final class UserRole
{
    public const CUSTOMER = 'customer';
    public const STAFF = 'staff';
    public const ADMIN = 'admin';

    public const BACK_OFFICE = [self::STAFF, self::ADMIN];
}
