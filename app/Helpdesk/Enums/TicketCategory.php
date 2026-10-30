<?php

namespace App\Helpdesk\Enums;

enum TicketCategory: string
{
    case OrderIssue = 'order_issue';
    case Delivery = 'delivery';
    case PaymentRefund = 'payment_refund';
    case ReturnReplacement = 'return_replacement';
    case ProductQuestion = 'product_question';
    case Account = 'account';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OrderIssue => 'Order issue',
            self::Delivery => 'Delivery & tracking',
            self::PaymentRefund => 'Payment & refund',
            self::ReturnReplacement => 'Return & replacement',
            self::ProductQuestion => 'Product question',
            self::Account => 'Account & login',
            self::Other => 'Other',
        };
    }

    /**
     * Categories offered to customers. Seller categories arrive in phase 2.
     *
     * @return list<self>
     */
    public static function forCustomers(): array
    {
        return self::cases();
    }
}
