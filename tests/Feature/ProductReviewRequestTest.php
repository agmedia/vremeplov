<?php

namespace Tests\Feature;

use App\Mail\ProductReviewRequestMail;
use App\Models\Back\Orders\Order;
use App\Models\ProductReviewInvitation;
use App\Services\ProductReviewRequestService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProductReviewRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['order_mail_deliveries', 'wishlist', 'reviews', 'product_review_invitations', 'order_products', 'products', 'order_history', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedInteger('order_status_id');
            $table->string('payment_fname');
            $table->string('payment_lname');
            $table->string('payment_email');
            $table->timestamps();
        });
        Schema::create('order_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('status');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('image')->nullable();
        });
        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_product_id')->nullable()->unique();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->text('message');
            $table->decimal('stars', 4, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('status')->default(false);
            $table->boolean('is_verified_purchase')->default(false);
            $table->timestamps();
        });
        Schema::create('product_review_invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->unique();
            $table->char('token_hash', 64)->unique();
            $table->string('recipient_email');
            $table->string('recipient_email_normalized');
            $table->string('recipient_name');
            $table->timestamp('eligible_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        DB::table('products')->insert([
            'id' => 10,
            'name' => 'Knjiga za recenziju',
            'image' => 'media/img/products/test.jpg',
        ]);

        config([
            'reviews.request_emails_enabled' => true,
            'reviews.request_delay_days' => 10,
            'reviews.request_order_lookback_days' => 60,
            'reviews.request_daily_limit' => 100,
            'reviews.request_max_attempts' => 3,
            'reviews.request_link_days' => 180,
            'reviews.minimum_interval_seconds' => 120,
            'reviews.eligible_status_ids' => [4, 9, 10],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recent_orders_due_for_ten_days_are_included_but_orders_older_than_sixty_days_are_not(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();

        $this->insertOrder(1, 4, 'kupac1@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');
        $this->insertOrder(2, 9, 'kupac2@example.test', '2026-07-20 10:00:00', '2026-08-25 11:00:00');
        $this->insertOrder(3, 4, 'staro@example.test', '2026-07-20 10:00:00', '2026-08-01 10:00:00');
        $this->insertOrder(4, 4, 'kasno@example.test', '2026-07-20 10:00:00', '2026-08-26 10:00:00');
        $this->insertOrder(5, 5, 'otkazano@example.test', '2026-07-20 10:00:00', '2026-08-25 12:00:00');
        $this->insertOrder(6, 4, 'prestaro@example.test', '2026-06-01 10:00:00', '2026-08-25 13:00:00');

        $this->artisan('reviews:send-requests')->assertExitCode(0);
        Carbon::setTestNow(now()->addSeconds(120));
        $this->artisan('reviews:send-requests')->assertExitCode(0);
        Carbon::setTestNow(now()->addSeconds(120));
        $this->artisan('reviews:send-requests')->assertExitCode(0);

        $this->assertSame(
            [1, 2, 3],
            DB::table('product_review_invitations')->orderBy('order_id')->pluck('order_id')->map(fn ($id) => (int) $id)->all()
        );
        Mail::assertSent(ProductReviewRequestMail::class, 3);

        $this->artisan('reviews:send-requests')->assertExitCode(0);
        $this->assertSame(3, DB::table('product_review_invitations')->count());
        Mail::assertSent(ProductReviewRequestMail::class, 3);
    }

    public function test_normalized_email_receives_only_one_request(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();

        $this->insertOrder(1, 4, ' Kupac@Example.test ', '2026-07-20 10:00:00', '2026-08-25 10:00:00');
        $this->insertOrder(2, 4, 'kupac@example.test', '2026-07-20 11:00:00', '2026-08-25 11:00:00');

        $this->artisan('reviews:send-requests')->assertExitCode(0);

        $this->assertSame(1, DB::table('product_review_invitations')->count());
        Mail::assertSent(ProductReviewRequestMail::class, 1);

        $result = app(ProductReviewRequestService::class)->send(Order::query()->findOrFail(2));
        $this->assertSame(ProductReviewRequestService::STATUS_SKIPPED, $result['status']);
        $this->assertSame('Poziv na ovu e-mail adresu već je poslan.', $result['message']);
    }

    public function test_dry_run_never_writes_or_sends_and_disabled_command_is_safe(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();
        $this->insertOrder(1, 4, 'kupac@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');

        config(['reviews.request_emails_enabled' => false]);

        $this->artisan('reviews:send-requests')->assertExitCode(0);
        $this->artisan('reviews:send-requests --dry-run')->assertExitCode(0);

        $this->assertSame(0, DB::table('product_review_invitations')->count());
        Mail::assertNothingSent();
    }

    public function test_daily_limit_caps_review_requests_across_repeated_runs(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();
        config(['reviews.request_daily_limit' => 1]);

        $this->insertOrder(1, 4, 'prvi@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');
        $this->insertOrder(2, 4, 'drugi@example.test', '2026-07-20 11:00:00', '2026-08-25 11:00:00');

        $this->artisan('reviews:send-requests')->assertExitCode(0);
        $this->artisan('reviews:send-requests')->assertExitCode(0);

        $this->assertSame(1, DB::table('product_review_invitations')->count());
        Mail::assertSent(ProductReviewRequestMail::class, 1);
    }

    public function test_review_mail_never_sends_more_than_once_per_two_minutes(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();

        $this->insertOrder(1, 4, 'prvi@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');
        $this->insertOrder(2, 4, 'drugi@example.test', '2026-07-20 11:00:00', '2026-08-25 11:00:00');

        $this->artisan('reviews:send-requests', ['--limit' => 100])->assertExitCode(0);
        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());

        Carbon::setTestNow(now()->addSeconds(119));
        $this->artisan('reviews:send-requests', ['--limit' => 100])->assertExitCode(0);
        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());

        Carbon::setTestNow(now()->addSecond());
        $this->artisan('reviews:send-requests', ['--limit' => 100])->assertExitCode(0);
        Mail::assertSent(ProductReviewRequestMail::class, 2);
        $this->assertSame(2, DB::table('product_review_invitations')->count());
    }

    public function test_due_order_mail_has_priority_over_review_mail(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();
        $this->insertOrder(1, 4, 'kupac@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');

        Schema::create('order_mail_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('type');
            $table->string('recipient');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        DB::table('order_mail_deliveries')->insert([
            'order_id' => 999,
            'type' => 'customer_confirmation',
            'recipient' => 'prioritet@example.test',
            'next_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('reviews:send-requests', ['--limit' => 1])->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('product_review_invitations')->count());

        DB::table('order_mail_deliveries')->update(['sent_at' => now()]);
        $this->artisan('reviews:send-requests', ['--limit' => 1])->assertExitCode(0);
        Mail::assertSent(ProductReviewRequestMail::class, 1);
    }

    public function test_ready_wishlist_mail_has_priority_over_review_mail(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Mail::fake();
        $this->insertOrder(1, 4, 'kupac@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('status')->default(true);
            $table->unsignedInteger('quantity')->default(1);
        });
        Schema::create('wishlist', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('email');
            $table->boolean('sent')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        DB::table('wishlist')->insert([
            'product_id' => 10,
            'email' => 'wishlist@example.test',
            'sent' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('reviews:send-requests', ['--limit' => 1])->assertExitCode(0);
        Mail::assertNothingSent();

        DB::table('wishlist')->update(['sent' => 1, 'sent_at' => now()]);
        $this->artisan('reviews:send-requests', ['--limit' => 1])->assertExitCode(0);
        Mail::assertSent(ProductReviewRequestMail::class, 1);
    }

    public function test_signed_invitation_accepts_one_pending_verified_review_per_item(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $this->insertOrder(1, 4, 'kupac@example.test', '2026-07-20 10:00:00', '2026-08-25 10:00:00');

        $token = str_repeat('a', 64);
        $invitationId = DB::table('product_review_invitations')->insertGetId([
            'order_id' => 1,
            'token_hash' => ProductReviewInvitation::hashToken($token),
            'recipient_email' => 'kupac@example.test',
            'recipient_email_normalized' => 'kupac@example.test',
            'recipient_name' => 'Ana Horvat',
            'eligible_at' => now()->subDays(10),
            'sent_at' => now(),
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            'product-review-invitations.show',
            now()->addDays(180),
            ['token' => $token]
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('Knjiga za recenziju')
            ->assertSee('Potvrđena kupnja');

        $this->post($url, [
            'order_product_id' => 1,
            'stars' => 5,
            'message' => 'Knjiga je stigla brzo i u odličnom stanju.',
        ])->assertRedirect($url);

        $this->assertDatabaseHas('reviews', [
            'order_product_id' => 1,
            'invitation_id' => $invitationId,
            'order_id' => 1,
            'product_id' => 10,
            'status' => 0,
            'is_verified_purchase' => 1,
        ]);
        $this->assertNotNull(DB::table('product_review_invitations')->where('id', $invitationId)->value('completed_at'));

        $this->post($url, [
            'order_product_id' => 1,
            'stars' => 1,
            'message' => 'Ovaj drugi unos ne smije prepisati prvi.',
        ])->assertRedirect($url);

        $this->assertSame(1, DB::table('reviews')->count());
        $this->assertSame(5.0, (float) DB::table('reviews')->value('stars'));
    }

    public function test_unsigned_and_canceled_order_links_are_rejected(): void
    {
        $this->insertOrder(1, 5, 'kupac@example.test', '2026-07-20 10:00:00', null);
        $token = str_repeat('b', 64);
        DB::table('product_review_invitations')->insert([
            'order_id' => 1,
            'token_hash' => ProductReviewInvitation::hashToken($token),
            'recipient_email' => 'kupac@example.test',
            'recipient_email_normalized' => 'kupac@example.test',
            'recipient_name' => 'Ana Horvat',
            'eligible_at' => now()->subDays(10),
            'sent_at' => now(),
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/zahtjev-za-recenziju/' . $token)->assertForbidden();

        $signed = URL::temporarySignedRoute(
            'product-review-invitations.show',
            now()->addDay(),
            ['token' => $token]
        );
        $this->get($signed)->assertStatus(410);
    }

    private function insertOrder(
        int $id,
        int $status,
        string $email,
        string $createdAt,
        ?string $sentAt
    ): void {
        DB::table('orders')->insert([
            'id' => $id,
            'user_id' => 0,
            'order_status_id' => $status,
            'payment_fname' => 'Ana',
            'payment_lname' => (string) $id,
            'payment_email' => $email,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        DB::table('order_products')->insert([
            'id' => $id,
            'order_id' => $id,
            'product_id' => 10,
            'name' => 'Knjiga za recenziju',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        if ($sentAt) {
            DB::table('order_history')->insert([
                'order_id' => $id,
                'user_id' => 0,
                'status' => 4,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }
    }
}
