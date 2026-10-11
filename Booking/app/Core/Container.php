<?php
declare(strict_types=1);

namespace SJT\Core;

use SJT\Repo\Bookings;
use SJT\Repo\Catalog;
use SJT\Service\Audit;
use SJT\Service\Auth;
use SJT\Service\Availability;
use SJT\Service\BookingForm;
use SJT\Service\BookingService;
use SJT\Service\InvoiceRenderer;
use SJT\Service\Notifier;
use SJT\Service\PaymentGateway;
use SJT\Service\UpiPayment;
use SJT\Service\WhatsAppClient;

/** Lazily builds and shares service objects for one request. */
final class Container
{
    private static array $i = [];

    public static function reset(): void
    {
        self::$i = [];
    }

    private static function once(string $k, callable $f): mixed
    {
        return self::$i[$k] ??= $f();
    }

    public static function bookings(): Bookings { return self::once('bookings', fn () => new Bookings(App::db())); }
    public static function catalog(): Catalog { return self::once('catalog', fn () => new Catalog(App::db())); }
    public static function audit(): Audit { return self::once('audit', fn () => new Audit(App::db())); }
    public static function limiter(): RateLimiter { return self::once('limiter', fn () => new RateLimiter(App::db())); }
    public static function auth(): Auth { return self::once('auth', fn () => new Auth(App::db(), self::limiter())); }
    public static function availability(): Availability { return self::once('avail', fn () => new Availability(App::db(), self::catalog(), App::settings())); }
    public static function form(): BookingForm { return self::once('form', fn () => new BookingForm(self::catalog(), self::availability(), App::settings())); }
    public static function upi(): UpiPayment { return self::once('upi', fn () => new UpiPayment(App::settings())); }
    public static function whatsapp(): WhatsAppClient { return self::once('wa', fn () => new WhatsAppClient()); }
    public static function notifier(): Notifier { return self::once('notifier', fn () => new Notifier(App::db(), self::bookings(), App::settings(), self::whatsapp())); }
    public static function invoices(): InvoiceRenderer { return self::once('invoices', fn () => new InvoiceRenderer(self::bookings(), App::settings(), self::upi())); }
    public static function gateway(): PaymentGateway { return self::once('gateway', fn () => new PaymentGateway(App::db())); }

    public static function service(): BookingService
    {
        return self::once('service', fn () => new BookingService(
            App::db(), self::bookings(), self::catalog(), self::availability(), App::settings(), self::audit(), self::notifier()
        ));
    }
}
