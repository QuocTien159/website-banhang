<?php

namespace App\Services;

final class GhnShipmentStatusMapper
{
    public function normalize(?string $rawStatus): ?string
    {
        if (!$rawStatus) {
            return null;
        }

        return match (strtolower(trim($rawStatus))) {
            'ready_to_pick' => 'waiting_pickup',
            'picking', 'money_collect_picking' => 'picking',
            'picked', 'storing', 'transporting', 'sorting' => 'in_transit',
            'delivering', 'money_collect_delivering' => 'delivering',
            'delivered' => 'delivered',
            'delivery_fail' => 'delivery_failed',
            'cancel' => 'cancelled',
            'waiting_to_return', 'return', 'return_transporting', 'return_sorting', 'returning' => 'returning',
            'returned' => 'returned',
            'return_fail', 'exception', 'damage', 'lost' => 'exception',
            default => 'unknown',
        };
    }

    public function isTerminal(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['delivered', 'cancelled', 'returned'], true);
    }
}
