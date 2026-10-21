<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Staff;
use App\Support\ReceiptTemplates;
use Illuminate\Support\Str;
use Tests\AuthenticationTestCase;

class ReceiptTemplatesTest extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $staff = Staff::create(['name' => 'Receipt Cashier', 'username' => 'receipt-cashier', 'password' => 'password123',
            'role' => 'Staff', 'is_active' => true, 'permissions' => ['pos']]);
        $this->actingAs($staff, 'staff');
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'number' => 'TEST-'.Str::random(8), 'request_key' => (string) Str::uuid(),
            'staff_name' => 'Receipt Cashier', 'customer_name' => 'Test Buyer <script>alert(1)</script>',
            'type' => 'Walk-in', 'status' => 'Pending', 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'subtotal' => 1000, 'discount_amount' => 100, 'total_amount' => 900, 'paid_amount' => 900,
        ], $attributes));
        $order->items()->create(['name' => 'Test Cement', 'quantity' => 4, 'unit_price' => 250,
            'discount_percent' => 10, 'discount_amount' => 100, 'total_amount' => 900]);

        return $order;
    }

    private function payment(Order $order, float $amount, float $tendered)
    {
        return $order->payments()->create(['request_key' => (string) Str::uuid(), 'staff_name' => 'Receipt Cashier',
            'method' => 'Cash', 'amount' => $amount, 'tendered' => $tendered]);
    }

    private function document(Order $order, string $template, array $query = [])
    {
        return $this->get(route('staff.receipts.show', ['order' => $order, 'template' => $template] + $query));
    }

    public function test_print_button_selects_the_template_and_opens_the_print_dialog(): void
    {
        $cases = [
            [[], 'cash-sale'],
            [['payment_method' => 'Charge', 'paid_amount' => 300, 'payment_status' => 'Partial'], 'charge-sale'],
            [['type' => 'Delivery'], 'delivery'],
            [['type' => 'Delivery', 'payment_method' => 'Charge', 'paid_amount' => 0], 'delivery'],
            [['status' => 'Quotation', 'type' => 'Delivery', 'paid_amount' => 0], 'quotation'],
            [['status' => 'Held', 'paid_amount' => 0], 'invoice-preview'],
            [['status' => 'Cancelled'], 'invoice-preview'],
            [['payment_method' => 'Online'], 'payment'],
            [['payment_method' => 'Cheque'], 'payment'],
        ];
        foreach ($cases as [$attributes, $template]) {
            $order = $this->order($attributes);
            $payment = $this->payment($order, (float) $order->paid_amount, 1000);
            $payment->update(['method' => $order->payment_method === 'Charge' ? 'Cash' : $order->payment_method]);
            $parameters = ['order' => $order, 'template' => $template, 'print' => 1];
            if ($template === 'payment') {
                $parameters['payment'] = $payment->id;
            }
            $url = route('staff.receipts.print', $order);
            $this->get($url)->assertRedirect(route('staff.receipts.show', $parameters));
            $this->followingRedirects()->get($url)->assertOk()->assertViewHas('template', $template)
                ->assertViewHas('autoPrint', true)->assertSee('js/receipt-print.js');
            $this->get(route('staff.orders.show', $order))->assertOk()->assertSee('href="'.$url.'"', false)
                ->assertDontSee('Choose Receipt Format')->assertDontSee('data-print', false);
        }
    }

    public function test_default_payment_receipt_uses_latest_payment_and_row_link_uses_selected_payment(): void
    {
        $order = $this->order(['payment_method' => 'Online']);
        $first = $this->payment($order, 300, 300);
        $latest = $this->payment($order, 600, 600);
        $this->get(route('staff.receipts.print', $order))->assertRedirect(route('staff.receipts.show', [
            'order' => $order, 'template' => 'payment', 'print' => 1, 'payment' => $latest->id,
        ]));
        $this->get(route('staff.orders.show', $order))->assertOk()->assertSee(e(route('staff.receipts.show', [
            'order' => $order, 'template' => 'payment', 'payment' => $first->id, 'print' => 1,
        ])), false);
        $this->document($order, 'payment', ['payment' => $first->id])->assertOk()->assertDontSee('js/receipt-print.js');
    }

    public function test_legacy_print_uses_recorded_balances_without_guessing_payment_method_or_price(): void
    {
        $sale = Sale::create(['receipt_no' => 'LEGACY-PRINT', 'staff_name' => 'Old Cashier', 'item_name' => 'Old Item',
            'qty' => 2, 'total_amount' => 100, 'paid_amount' => 25, 'status' => 'Completed']);
        $url = route('staff.receipts.legacy', ['receipt' => $sale->receipt_no]);
        $this->get($url)->assertOk()->assertViewHas('template', 'charge-sale')->assertSee('₱75.00')
            ->assertSee('Old Item')->assertSee('Not recorded')->assertSee('js/receipt-print.js')->assertDontSee('Cash tendered');
        $sale->update(['paid_amount' => 100]);
        $this->get($url)->assertOk()->assertViewHas('template', 'invoice-preview')->assertSee('₱100.00');
        $this->get(route('staff.receipts.legacy', ['receipt' => 'missing']))->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_all_seven_original_pdfs_are_available_and_unknown_templates_are_rejected(): void
    {
        $this->assertCount(7, ReceiptTemplates::all());
        $library = $this->get(route('staff.receipts.index'))->assertOk();
        foreach (ReceiptTemplates::all() as $key => $definition) {
            $library->assertSee($definition['label']);
            $response = $this->get(route('staff.receipts.sample', $key))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertSame(realpath(resource_path('receipts/'.$definition['file'])), $response->baseResponse->getFile()->getRealPath());
        }
        $this->get(route('staff.receipts.sample', 'unknown'))->assertNotFound();
    }

    public function test_cash_sale_prints_saved_totals_discount_and_correct_change(): void
    {
        $order = $this->order();
        $this->payment($order, 900, 1000);
        $this->document($order, 'cash-sale')->assertOk()->assertSee('CASH SALES TRANSACTION SLIP')
            ->assertSee('Test Cement')->assertSee('₱900.00')->assertSee('₱1,000.00')->assertSee('₱100.00')
            ->assertSee('NOT A BIR TAX INVOICE')->assertDontSee('TXN-2026-00125');
        $this->document($order, 'invoice-preview')->assertOk()->assertSee('NOT VALID FOR ISSUANCE')
            ->assertSee(e($order->customer_name), false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('staff.receipts.index', ['order' => $order->id]))->assertOk()->assertSee('Open Receipt');
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertEquals(900, $order->fresh()->paid_amount);
    }

    public function test_payment_receipt_uses_balance_at_that_payment_and_rejects_other_orders_payments(): void
    {
        $order = $this->order(['payment_method' => 'Charge', 'paid_amount' => 700, 'payment_status' => 'Partial']);
        $first = $this->payment($order, 300, 300);
        $second = $this->payment($order, 400, 500);
        $this->document($order, 'payment', ['payment' => $first->id])->assertOk()
            ->assertViewHas('previousBalance', 900)->assertViewHas('remainingBalance', 600);
        $this->document($order, 'payment', ['payment' => $second->id])->assertOk()
            ->assertViewHas('previousBalance', 600)->assertViewHas('remainingBalance', 200)->assertSee('₱100.00');
        $other = $this->order();
        $foreignPayment = $this->payment($other, 900, 900);
        $this->document($order, 'payment', ['payment' => $foreignPayment->id])->assertNotFound();
        $this->document($order, 'payment')->assertSessionHasErrors('payment');
        $this->document($order, 'charge-sale')->assertOk()->assertSee('BALANCE DUE')->assertSee('₱200.00')->assertSee('Not specified');
        $this->document($order, 'cash-sale')->assertNotFound();
    }

    public function test_delivery_quotation_and_return_form_use_their_own_fields_without_recording_transactions(): void
    {
        $order = $this->order(['type' => 'Delivery', 'address' => 'Silang, Cavite', 'delivery_fee' => 500,
            'total_amount' => 1400, 'paid_amount' => 0, 'payment_status' => 'Unpaid', 'payment_method' => 'Charge']);
        $this->document($order, 'delivery')->assertOk()->assertSee('DELIVERY SLIP')->assertSee('Silang, Cavite')
            ->assertSee('Received by')->assertSee('Pending')->assertDontSee('₱');
        $this->document($order, 'return')->assertOk()->assertSee('SALES RETURN SLIP')->assertSee('MANUAL FORM / PENDING APPROVAL')
            ->assertSee($order->number)->assertSee('Authorized by')->assertDontSee('₱1,400.00');
        $this->document($order, 'quotation')->assertNotFound();
        $quote = $this->order(['status' => 'Quotation', 'paid_amount' => 0, 'payment_status' => 'Unpaid']);
        $this->document($quote, 'quotation')->assertOk()->assertSee('ESTIMATED TOTAL')->assertSee('Not specified');
        foreach (['cash-sale', 'charge-sale', 'delivery', 'payment', 'return'] as $key) {
            $this->document($quote, $key)->assertNotFound();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertSame('Quotation', $quote->fresh()->status);
    }

    public function test_cancelled_and_held_orders_only_allow_invoice_preview(): void
    {
        foreach (['Cancelled', 'Held'] as $status) {
            $order = $this->order(['status' => $status]);
            $this->document($order, 'invoice-preview')->assertOk()->assertSee($status);
            foreach (array_diff(array_keys(ReceiptTemplates::all()), ['invoice-preview']) as $key) {
                $this->document($order, $key)->assertNotFound();
            }
        }
    }

    public function test_return_slip_uses_selected_order_items_and_discounted_prices_without_posting_a_refund(): void
    {
        $order = $this->order();
        $item = $order->items->first();
        $query = ['quantities' => [$item->id => 2], 'reason' => 'Damaged bags'];
        $this->document($order, 'return', $query)->assertOk()->assertSee('Damaged bags')->assertSee('Receipt Cashier')
            ->assertSee('Test Cement')->assertSee('₱450.00')->assertSee('Pending approval')->assertViewHas('returnTotal', 450);
        $this->document($order, 'return', ['quantities' => [$item->id => 5], 'reason' => 'Damaged'])->assertSessionHasErrors('quantities');
        $this->document($order, 'return', ['quantities' => [$item->id => 0], 'reason' => 'Damaged'])->assertSessionHasErrors('quantities');
        $this->document($order, 'return', ['quantities' => [$item->id => -1], 'reason' => 'Damaged'])->assertSessionHasErrors('quantities.'.$item->id);
        $foreignItem = $this->order()->items->first();
        $this->document($order, 'return', ['quantities' => [$foreignItem->id => 1], 'reason' => 'Damaged'])->assertSessionHasErrors('quantities');
        $this->assertEquals(900, $order->fresh()->paid_amount);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('sales', 0);
    }
}
