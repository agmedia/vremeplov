<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireAdministrator;
use App\Models\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdministratorMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_role_is_allowed(): void
    {
        $user = $this->userWithRole('admin');
        $request = $this->requestFor($user);

        $response = (new RequireAdministrator())->handle($request, function () {
            return response('allowed');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    public function test_master_role_is_allowed(): void
    {
        $user = $this->userWithRole('master');
        $request = $this->requestFor($user);

        $response = (new RequireAdministrator())->handle($request, function () {
            return response('allowed');
        });

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @dataProvider restrictedRoleProvider
     */
    public function test_non_administrator_roles_are_rejected(?string $role): void
    {
        $user = $role === null ? null : $this->userWithRole($role);

        try {
            (new RequireAdministrator())->handle($this->requestFor($user), function () {
                return response('not allowed');
            });

            $this->fail('Restricted role reached an administrator-only action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function restrictedRoleProvider(): array
    {
        return [
            'guest' => [null],
            'customer' => ['customer'],
            'editor' => ['editor'],
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $storedRole = Bouncer::role()->create([
            'name' => $role,
            'title' => ucfirst($role),
        ]);
        Bouncer::assign($storedRole)->to($user);
        Bouncer::refresh();

        return $user;
    }

    private function requestFor(?User $user): Request
    {
        $request = Request::create('/api/v2/settings/app/payment/store', 'POST');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $request;
    }
}
