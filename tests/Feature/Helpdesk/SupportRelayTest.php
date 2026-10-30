<?php

use App\Enums\SellerStatus;
use App\Helpdesk\Enums\MessageAuthor;
use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketCategory;
use App\Helpdesk\Enums\TicketStatus;
use App\Helpdesk\Models\SupportTicket;
use App\Helpdesk\Services\AnonymityGuard;
use App\Helpdesk\Services\HelpdeskService;
use App\Mail\SupportTicketMail;
use App\Models\Admin;
use App\Models\SellerProfile;
use App\Models\User;
use App\Orders\Enums\OrderStatus;
use App\Orders\Enums\PaymentMethod;
use App\Orders\Enums\PaymentStatus;
use App\Orders\Enums\SellerOrderStatus;
use App\Orders\Models\Order;
use App\Orders\Models\SellerOrder;
use App\Support\AdminPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * A customer (Asha Verma) with an order from one seller (Toy Kingdom), and a customer ticket about it.
 *
 * @return array{customer: User, seller: User, sellerOrder: SellerOrder, ticket: SupportTicket, admin: Admin}
 */
function relayScenario(): array
{
    $customer = User::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);
    $seller = User::factory()->create(['email' => 'owner@toykingdom.example']);
    $profile = SellerProfile::factory()->create(['user_id' => $seller->id, 'status' => SellerStatus::Approved, 'store_name' => 'Toy Kingdom']);

    $order = Order::create([
        'order_number' => 'KH-ORD-RELAY',
        'user_id' => $customer->id,
        'checkout_intent_id' => 'intent-relay',
        'status' => OrderStatus::Placed,
        'delivery_address' => [],
        'delivery_state' => 'Karnataka',
        'payment_method' => PaymentMethod::CashOnDelivery,
        'payment_status' => PaymentStatus::Pending,
        'items_mrp_total' => 50000,
        'discount_total' => 0,
        'delivery_fee' => 0,
        'grand_total' => 50000,
        'placed_at' => now(),
    ]);
    $sellerOrder = SellerOrder::create([
        'order_id' => $order->id,
        'seller_profile_id' => $profile->id,
        'seller_store_name' => 'Toy Kingdom',
        'status' => SellerOrderStatus::Delivered,
        'subtotal' => 50000,
        'discount_total' => 0,
        'grand_total' => 50000,
        'placed_at' => now(),
    ]);

    $ticket = app(HelpdeskService::class)->openCustomerTicket(
        user: $customer,
        category: TicketCategory::OrderIssue,
        subject: 'Wrong size received',
        body: 'I ordered size M but got size L.',
        orderNumber: 'KH-ORD-RELAY',
        attachments: [UploadedFile::fake()->image('asha_box_photo.jpg', 200, 200)],
    );

    $admin = Admin::factory()->create(['is_active' => true, 'name' => 'Ravi Agent']);
    $role = Role::findOrCreate('Relay '.uniqid(), 'admin');
    $role->givePermissionTo([AdminPermissions::SUPPORT_VIEW, AdminPermissions::SUPPORT_REPLY]);
    $admin->assignRole($role);

    return ['customer' => $customer, 'seller' => $seller->fresh(), 'sellerOrder' => $sellerOrder, 'ticket' => $ticket, 'admin' => $admin->fresh()];
}

function askRelaySeller(array $s, string $body = 'The customer reports size L instead of M. Do you have M in stock?', array $refs = []): SupportTicket
{
    return app(HelpdeskService::class)->askSeller(
        customerTicket: $s['ticket']->fresh(),
        admin: $s['admin'],
        sellerOrderId: $s['sellerOrder']->id,
        category: TicketCategory::SellerOrders,
        subject: 'Order KH-ORD-RELAY: wrong size',
        body: $body,
        attachmentRefs: $refs,
    );
}

beforeEach(function () {
    Artisan::call('permissions:sync');
    Mail::fake();
    Storage::fake('local');
});

test('anonymity guard blocks contact details and the other side\'s identity', function () {
    $guard = new AnonymityGuard;
    $customer = AnonymityGuard::customerIdentifiers('Asha Verma', 'asha@example.com', 'SUP-261030-AAAA');

    expect($guard->problems('Customer says size is wrong.', $customer))->toBe([])
        ->and($guard->problems('Mail her at asha@example.com', $customer))->toContain('an email address')
        ->and($guard->problems('Pay to asha.v@okaxis', $customer))->toContain('a UPI ID')
        ->and($guard->problems('Call +91 98765 43210', $customer))->toContain('a phone number')
        ->and($guard->problems('Call 9876543210', $customer))->toContain('a phone number')
        ->and($guard->problems('Asha says it is wrong', $customer))->toContain('the customer\'s first name')
        ->and($guard->problems('See SUP-261030-AAAA', $customer))->toContain('the customer\'s ticket number')
        ->and($guard->problems('Order KH-20261025-OL3MKL, ₹1,299, pincode 560001', $customer))->toBe([]);
});

test('staff ask the seller: seller ticket opened anonymously and customer ticket waits on the seller', function () {
    $s = relayScenario();
    $photoRef = $s['ticket']->messages()->first()->id.':0';

    Livewire::actingAs($s['admin'], 'admin')
        ->test('pages::admin.support-show', ['ticket' => $s['ticket']->ticket_number])
        ->call('startAsking')
        ->assertSet('askSellerOrderId', $s['sellerOrder']->id)
        ->set('askBody', 'The customer reports size L instead of M. Do you have M in stock?')
        ->set('askAttachments', [$photoRef])
        ->call('askSeller')
        ->assertHasNoErrors();

    $customerTicket = $s['ticket']->fresh();
    $sellerTicket = $customerTicket->linkedTicket;

    expect($customerTicket->status)->toBe(TicketStatus::AwaitingSeller)
        ->and($sellerTicket->requester_type)->toBe(RequesterType::Seller)
        ->and($sellerTicket->user_id)->toBe($s['seller']->id)
        ->and($sellerTicket->linked_ticket_id)->toBe($customerTicket->id)
        ->and($sellerTicket->status)->toBe(TicketStatus::Pending)
        ->and($sellerTicket->reply_due_at)->not->toBeNull();

    $first = $sellerTicket->messages()->where('is_internal', false)->sole();
    expect($first->author_type)->toBe(MessageAuthor::Staff)
        ->and($first->attachments[0]['name'])->toBe('attachment-1.jpg');
    Storage::disk('local')->assertExists($first->attachments[0]['path']);

    // The seller sees only the marketplace
    $this->actingAs($s['seller'], 'web')->get(route('seller.support.show', ['ticket' => $sellerTicket->ticket_number]))
        ->assertOk()
        ->assertSee('Support')
        ->assertSee('Please reply by')
        ->assertDontSee('Asha')
        ->assertDontSee('asha@example.com')
        ->assertDontSee($customerTicket->ticket_number)
        ->assertDontSee('asha_box_photo');

    Mail::assertQueued(SupportTicketMail::class, fn ($mail) => $mail->event === 'seller_action' && $mail->hasTo('owner@toykingdom.example'));

    // The customer sees the status, not the seller
    $this->actingAs($s['customer'], 'web')->get(route('support.show', ['ticket' => $customerTicket->ticket_number]))
        ->assertSee('We are checking with the seller')
        ->assertDontSee($sellerTicket->ticket_number);
});

test('asking the seller is refused when the text identifies the customer', function () {
    $s = relayScenario();

    expect(fn () => askRelaySeller($s, 'Asha Verma got the wrong size, her number is 9876543210'))
        ->toThrow(DomainException::class, 'Remove');

    expect(SupportTicket::query()->where('requester_type', 'seller')->count())->toBe(0)
        ->and($s['ticket']->fresh()->status)->toBe(TicketStatus::Open);
});

test('a seller reply brings the customer ticket back to staff marked "Seller replied"', function () {
    $s = relayScenario();
    $sellerTicket = askRelaySeller($s);

    app(HelpdeskService::class)->replyAsRequester($sellerTicket, $s['seller'], 'M is out of stock, we can refund.');

    $customerTicket = $s['ticket']->fresh();
    expect($customerTicket->status)->toBe(TicketStatus::Open)
        ->and($customerTicket->attention)->toBe('seller_replied')
        ->and($sellerTicket->fresh()->reply_due_at)->toBeNull();

    Livewire::actingAs($s['admin'], 'admin')
        ->test('pages::admin.support-index')
        ->assertSee('Seller replied');
});

test('staff forward the seller\'s reply to the customer without the store name', function () {
    $s = relayScenario();
    $sellerTicket = askRelaySeller($s);
    $reply = app(HelpdeskService::class)->replyAsRequester($sellerTicket, $s['seller'], 'Toy Kingdom here: M is out of stock, we can refund.');

    // Forwarding as-is is blocked: it names the store
    Livewire::actingAs($s['admin'], 'admin')
        ->test('pages::admin.support-show', ['ticket' => $sellerTicket->ticket_number])
        ->call('startForward', $reply->id)
        ->assertSet('forwardBody', 'Toy Kingdom here: M is out of stock, we can refund.')
        ->call('forward')
        ->assertHasErrors('forward')
        ->set('forwardBody', 'The seller has no size M left, so we will refund you in full.')
        ->set('forwardStatusAfter', 'pending')
        ->call('forward')
        ->assertHasNoErrors();

    $customerTicket = $s['ticket']->fresh();
    expect($customerTicket->status)->toBe(TicketStatus::Pending)
        ->and($customerTicket->attention)->toBeNull();

    $this->actingAs($s['customer'], 'web')->get(route('support.show', ['ticket' => $customerTicket->ticket_number]))
        ->assertSee('we will refund you in full')
        ->assertDontSee('Toy Kingdom here')
        ->assertDontSee($sellerTicket->ticket_number);

    Mail::assertQueued(SupportTicketMail::class, fn ($mail) => $mail->event === 'staff_reply' && $mail->hasTo('asha@example.com'));
});

test('forwarding the customer\'s follow-up to the seller parks the customer ticket again', function () {
    $s = relayScenario();
    $sellerTicket = askRelaySeller($s);
    app(HelpdeskService::class)->replyAsRequester($sellerTicket, $s['seller'], 'Can the customer send a photo of the tag?');
    $followUp = app(HelpdeskService::class)->replyAsRequester($s['ticket']->fresh(), $s['customer'], 'Here is the tag.', [UploadedFile::fake()->image('tag.png')]);

    app(HelpdeskService::class)->forward($followUp->fresh(), $s['admin'], 'The customer sent this photo of the tag.', [0]);

    $forwarded = $sellerTicket->messages()->where('is_internal', false)->reorder('id', 'desc')->first();
    expect($s['ticket']->fresh()->status)->toBe(TicketStatus::AwaitingSeller)
        ->and($sellerTicket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and($forwarded->attachments[0]['name'])->toBe('attachment-1.png');
});

test('internal notes and staff messages cannot be forwarded', function () {
    $s = relayScenario();
    askRelaySeller($s);
    $note = app(HelpdeskService::class)->replyAsStaff($s['ticket']->fresh(), $s['admin'], 'Customer seems genuine', internal: true);

    expect(fn () => app(HelpdeskService::class)->forward($note->fresh(), $s['admin'], 'anything'))
        ->toThrow(DomainException::class);
});

test('sellers get one reminder before the deadline and show as overdue after it', function () {
    $s = relayScenario();
    $sellerTicket = askRelaySeller($s);

    $this->artisan('helpdesk:remind-sellers')->assertSuccessful();
    Mail::assertNotQueued(SupportTicketMail::class, fn ($mail) => $mail->event === 'seller_reminder');

    $this->travel(30)->hours();
    $this->artisan('helpdesk:remind-sellers')->assertSuccessful();
    $this->artisan('helpdesk:remind-sellers')->assertSuccessful();
    Mail::assertQueuedCount(3); // received, seller_action and exactly one reminder
    Mail::assertQueued(SupportTicketMail::class, fn ($mail) => $mail->event === 'seller_reminder');

    $this->travel(20)->hours();
    expect($sellerTicket->fresh()->isSellerOverdue())->toBeTrue();

    Livewire::actingAs($s['admin'], 'admin')
        ->test('pages::admin.support-index')
        ->set('status', 'awaiting_seller')
        ->assertSee('Seller overdue');
});

test('only a customer ticket about an order with no open link can ask the seller', function () {
    $s = relayScenario();
    askRelaySeller($s);

    expect(fn () => askRelaySeller($s))->toThrow(DomainException::class, 'already linked');

    $noOrder = app(HelpdeskService::class)->openCustomerTicket($s['customer'], TicketCategory::Account, 'Login trouble', 'I cannot sign in to my account.');
    expect(fn () => app(HelpdeskService::class)->askSeller($noOrder, $s['admin'], $s['sellerOrder']->id, TicketCategory::SellerOrders, 'Subject here', 'Body text'))
        ->toThrow(DomainException::class);
});
