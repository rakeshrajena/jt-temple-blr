<?php
declare(strict_types=1);

namespace SJT\OnlinePay;

use SJT\Core\Env;
use SJT\OnlinePay\Adapters\NullAdapter;
use SJT\OnlinePay\Adapters\RazorpayAdapter;
use SJT\OnlinePay\Contract\GatewayAdapter;

/** Resolves PAYMENT_GATEWAY in .env to an adapter instance. */
final class GatewayFactory
{
    public static function create(): GatewayAdapter
    {
        $id = strtolower(trim((string) Env::get('PAYMENT_GATEWAY', 'none')));
        return match ($id) {
            'razorpay' => new RazorpayAdapter(),
            default => new NullAdapter(),
        };
    }
}
