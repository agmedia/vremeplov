<?php

namespace Tests\Feature;

use App\Mail\WishlistArrived;
use App\Models\Back\Marketing\Wishlist;
use App\Services\WishlistNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WishlistNotificationServiceTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'wishlist_testing',
            'database.connections.wishlist_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
        ]);
        DB::purge('wishlist_testing');
        Cache::clear();

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('image')->nullable();
            $table->string('url')->nullable();
            $table->integer('quantity')->default(0);
            $table->boolean('status')->default(true);
        });
        Schema::create('wishlist', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('email');
            $table->unsignedBigInteger('product_id');
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('wishlist_testing');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_ready_notification_is_sent_once_and_marked_only_after_sending(): void
    {
        Mail::fake();
        $wishlist = $this->wishlist(1, 2);
        $service = app(WishlistNotificationService::class);

        $this->assertSame(WishlistNotificationService::STATUS_SENT, $service->send($wishlist)['status']);
        $this->assertSame(WishlistNotificationService::STATUS_SKIPPED, $service->send($wishlist)['status']);

        $wishlist->refresh();
        $this->assertSame(1, (int) $wishlist->sent);
        $this->assertSame(0, (int) $wishlist->status);
        $this->assertNotNull($wishlist->sent_at);
        Mail::assertSent(WishlistArrived::class, 1);
        Mail::assertSent(WishlistArrived::class, function ($mail) {
            return $mail->hasTo('kupac@example.test');
        });
    }

    public function test_unavailable_product_is_never_sent_or_marked(): void
    {
        Mail::fake();
        $wishlist = $this->wishlist(1, 0);

        $result = app(WishlistNotificationService::class)->send($wishlist);

        $this->assertSame(WishlistNotificationService::STATUS_SKIPPED, $result['status']);
        $this->assertSame(0, (int) $wishlist->fresh()->sent);
        Mail::assertNothingSent();
    }

    public function test_ready_and_waiting_scopes_match_current_stock(): void
    {
        $ready = $this->wishlist(1, 1);
        $waiting = $this->wishlist(2, 0);

        $this->assertSame([$ready->id], Wishlist::query()->readyToSend()->pluck('id')->all());
        $this->assertSame([$waiting->id], Wishlist::query()->waitingForStock()->pluck('id')->all());
    }

    public function test_check_command_never_sends_email(): void
    {
        Mail::fake();
        $this->wishlist(1, 1);

        $this->artisan('check:wishlist')
            ->expectsOutput('Spremno za ručno slanje: 1. Automatsko slanje je isključeno.')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, (int) Wishlist::query()->value('sent'));
    }

    private function wishlist(int $productId, int $quantity): Wishlist
    {
        DB::table('products')->insert([
            'id' => $productId,
            'name' => 'Knjiga ' . $productId,
            'sku' => 'SKU-' . $productId,
            'image' => null,
            'url' => 'knjiga-' . $productId,
            'quantity' => $quantity,
            'status' => 1,
        ]);

        return Wishlist::query()->create([
            'user_id' => 0,
            'email' => 'kupac@example.test',
            'product_id' => $productId,
            'sent' => 0,
            'status' => 1,
        ]);
    }
}
