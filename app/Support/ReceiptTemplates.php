<?php

namespace App\Support;

use App\Models\Order;

class ReceiptTemplates
{
    public static function all(): array
    {
        return [
            'cash-sale' => ['label' => 'Cash Sale', 'title' => 'CASH SALES TRANSACTION SLIP', 'file' => '01_Cash_Sale.pdf', 'note' => 'This slip is a POS record, not a tax invoice.'],
            'charge-sale' => ['label' => 'Charge Sale', 'title' => 'CHARGE SALES TRANSACTION SLIP', 'file' => '02_Charge_Sale.pdf', 'note' => 'Credit record only; tax invoice issued separately.'],
            'delivery' => ['label' => 'Delivery', 'title' => 'DELIVERY SLIP', 'file' => '03_Delivery.pdf', 'note' => 'Acknowledges goods delivered; not proof of payment.'],
            'payment' => ['label' => 'Payment', 'title' => 'PAYMENT ACKNOWLEDGMENT', 'file' => '04_Payment.pdf', 'note' => 'Acknowledgment of collection; not a tax invoice.'],
            'return' => ['label' => 'Return', 'title' => 'SALES RETURN SLIP', 'file' => '05_Return.pdf', 'note' => 'Return adjustments require approval and records.'],
            'quotation' => ['label' => 'Quotation', 'title' => 'SALES QUOTATION', 'file' => '06_Quotation.pdf', 'note' => 'Quotation only. Not a sale, invoice or payment receipt.'],
            'invoice-preview' => ['label' => 'Invoice Preview', 'title' => 'INVOICE DATA PREVIEW', 'file' => '07_Invoice_Preview.pdf', 'note' => 'NON-ISSUABLE MOCKUP. Required invoice numbering, taxpayer data, tax detail and approvals are not configured.'],
        ];
    }

    public static function forOrder(Order $order): array
    {
        $keys = ['invoice-preview'];
        if ($order->status === 'Quotation') {
            $keys[] = 'quotation';
        }
        if (! $order->isDraft() && $order->status !== 'Cancelled') {
            if ($order->payment_method === 'Cash' && $order->balance == 0
                && $order->payments->isNotEmpty() && $order->payments->every(fn ($payment) => $payment->method === 'Cash')) {
                $keys[] = 'cash-sale';
            }
            if ($order->payment_method === 'Charge' || $order->balance > 0) {
                $keys[] = 'charge-sale';
            }
            if ($order->type === 'Delivery') {
                $keys[] = 'delivery';
            }
            if ($order->payments->isNotEmpty()) {
                $keys[] = 'payment';
            }
            $keys[] = 'return';
        }

        return array_intersect_key(self::all(), array_flip($keys));
    }

    public static function defaultForOrder(Order $order): string
    {
        $available = self::forOrder($order);
        foreach (['quotation', 'delivery', 'charge-sale', 'cash-sale', 'payment', 'invoice-preview'] as $template) {
            if (isset($available[$template])) {
                return $template;
            }
        }

        return 'invoice-preview';
    }
}
