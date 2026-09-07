<?php

namespace Tests\Feature;

use App\Mail\ProductReviewRequestMail;
use App\Models\ProductReviewBackfill;
use App\Models\ProductReviewBackfillItem;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductReviewBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'product_review_backfill_items',
            'product_review_backfills',
            'reviews',
            'product_review_invitations',
            'order_products',
            'products',
            'order_history',
            'orders',
        ] as $table) {
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
        Schema::create('product_review_backfills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('requested_limit');
            $table->unsignedSmallInteger('interval_seconds')->default(5);
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('product_review_backfill_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('backfill_id');
            $table->unsignedBigInteger('order_id');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['backfill_id', 'order_id']);
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
            'reviews.eligible_status_ids' => [4, 9, 10],
            'reviews.backfill_max_orders' => 5000,
            'reviews.backfill_default_interval_seconds' => 5,
            'reviews.backfill_interval_options' => [5],
            'reviews.backfill_run_seconds' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backfill_only_queues_selected_historical_period_and_sends_nothing_on_creation(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        Mail::fake();

        $this->insertOrder(1, 'stari1@example.test', '2026-05-02 10:00:00', '2026-05-10 10:00:00');
        $this->insertOrder(2, 'stari2@example.test', '2026-05-20 10:00:00', '2026-06-10 10:00:00');
        $this->insertOrder(3, 'novi@example.test', '2026-07-20 10:00:00', '2026-08-20 10:00:00');

        $this->artisan('reviews:backfill', [
            '--from' => '2026-05-01',
            '--to' => '2026-06-30',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('product_review_backfills', [
            'eligible_count' => 2,
            'total_count' => 2,
            'status' => ProductReviewBackfill::STATUS_PENDING,
        ]);
        $this->assertSame(
            [1, 2],
            DB::table('product_review_backfill_items')->orderBy('order_id')
                ->pluck('order_id')->map(fn ($id) => (int) $id)->all()
        );

        $this->artisan('reviews:process-backfills', ['--max-seconds' => 1])->assertExitCode(0);

        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());
    }

    public function test_automatic_and_historical_modules_cannot_send_twice_to_same_normalized_email(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        Mail::fake();

        $this->insertOrder(1, ' Shared@Example.test ', '2026-05-02 10:00:00', '2026-05-10 10:00:00');

        $this->artisan('reviews:backfill', [
            '--from' => '2026-05-01',
            '--to' => '2026-05-31',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(0);

        $this->insertOrder(2, 'shared@example.test', '2026-07-20 10:00:00', '2026-08-20 10:00:00');
        $this->artisan('reviews:send-requests')->assertExitCode(0);
        $this->artisan('reviews:process-backfills', ['--max-seconds' => 1])->assertExitCode(0);

        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());
        $this->assertDatabaseHas('product_review_backfill_items', [
            'order_id' => 1,
            'status' => ProductReviewBackfillItem::STATUS_SKIPPED,
        ]);
        $this->assertDatabaseHas('product_review_backfills', [
            'processed_count' => 1,
            'sent_count' => 0,
            'skipped_count' => 1,
            'status' => ProductReviewBackfill::STATUS_COMPLETED,
        ]);
    }

    public function test_completed_historical_email_cannot_be_queued_or_sent_again(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        Mail::fake();

        $this->insertOrder(1, ' Repeat@Example.test ', '2026-05-02 10:00:00', '2026-05-10 10:00:00');
        $this->artisan('reviews:backfill', [
            '--from' => '2026-05-01',
            '--to' => '2026-05-31',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(0);
        $this->artisan('reviews:process-backfills', ['--max-seconds' => 1])->assertExitCode(0);

        $this->insertOrder(2, 'repeat@example.test', '2026-06-02 10:00:00', '2026-06-10 10:00:00');
        $this->artisan('reviews:backfill', [
            '--from' => '2026-06-01',
            '--to' => '2026-06-30',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(0);

        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());
        $this->assertSame(1, DB::table('product_review_backfills')->count());
        $this->assertSame(1, DB::table('product_review_backfill_items')->count());
    }

    public function test_automatic_process_skips_email_already_sent_by_historical_module(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        Mail::fake();

        $this->insertOrder(1, ' Prior@Example.test ', '2026-05-02 10:00:00', '2026-05-10 10:00:00');
        $this->artisan('reviews:backfill', [
            '--from' => '2026-05-01',
            '--to' => '2026-05-31',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(0);
        $this->artisan('reviews:process-backfills', ['--max-seconds' => 1])->assertExitCode(0);

        $this->insertOrder(2, 'prior@example.test', '2026-07-20 10:00:00', '2026-08-20 10:00:00');
        $this->artisan('reviews:send-requests')->assertExitCode(0);

        Mail::assertSent(ProductReviewRequestMail::class, 1);
        $this->assertSame(1, DB::table('product_review_invitations')->count());
    }

    public function test_historical_command_rejects_period_that_overlaps_regular_sixty_day_window(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        Mail::fake();

        $this->insertOrder(1, 'novi@example.test', '2026-07-20 10:00:00', '2026-08-20 10:00:00');

        $this->artisan('reviews:backfill', [
            '--from' => '2026-07-01',
            '--to' => '2026-08-20',
            '--limit' => 100,
            '--interval' => 5,
            '--yes' => true,
        ])->assertExitCode(1);

        $this->assertSame(0, DB::table('product_review_backfills')->count());
        Mail::assertNothingSent();
    }

    public function test_backfill_routes_are_protected_by_master_email_middleware(): void
    {
        foreach ([
            'product-review-backfills.index',
            'product-review-backfills.store',
            'product-review-backfills.cancel',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('review.backfill.admin', $route->gatherMiddleware());
        }
    }

    private function insertOrder(
        int $id,
        string $email,
        string $createdAt,
        string $sentAt,
        int $status = 4
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
        DB::table('order_history')->insert([
            'order_id' => $id,
            'user_id' => 0,
            'status' => 4,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }
}
