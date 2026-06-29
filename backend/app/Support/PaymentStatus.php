<?php

namespace App\Support;

final class PaymentStatus
{
    public const COD_PENDING = 'cod_pending';
    public const PENDING_PAYMENT = 'pending_payment';
    public const WAITING_ADMIN_CONFIRMATION = 'waiting_admin_confirmation';
    public const PAID = 'paid';
    public const PAYMENT_NOT_RECEIVED = 'payment_not_received';
}
