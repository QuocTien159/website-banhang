<?php

namespace App\Support;

final class OrderStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const PREPARING = 'preparing';
    public const READY_TO_SHIP = 'ready_to_ship';
    public const HANDED_TO_CARRIER = 'handed_to_carrier';
    public const COMPLETED = 'completed';
    public const RETURNING = 'returning';
    public const RETURNED = 'returned';

    // Legacy statuses are kept for old manual-delivery orders only.
    public const SHIPPING = 'shipping';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    public const FLOW = [
        self::PENDING,
        self::CONFIRMED,
        self::PREPARING,
        self::READY_TO_SHIP,
        self::HANDED_TO_CARRIER,
        self::COMPLETED,
    ];

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::PREPARING,
        self::READY_TO_SHIP,
        self::HANDED_TO_CARRIER,
        self::COMPLETED,
        self::RETURNING,
        self::RETURNED,
        self::SHIPPING,
        self::DELIVERED,
        self::CANCELLED,
    ];

    public const FULFILLED = [self::COMPLETED, self::DELIVERED];

    public static function isFulfilled(?string $status): bool
    {
        return in_array($status, self::FULFILLED, true);
    }
}
