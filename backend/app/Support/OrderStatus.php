<?php

namespace App\Support;

final class OrderStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const SHIPPING = 'shipping';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    public const FLOW = [
        self::PENDING,
        self::CONFIRMED,
        self::SHIPPING,
        self::DELIVERED,
    ];

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::SHIPPING,
        self::DELIVERED,
        self::CANCELLED,
    ];
}
