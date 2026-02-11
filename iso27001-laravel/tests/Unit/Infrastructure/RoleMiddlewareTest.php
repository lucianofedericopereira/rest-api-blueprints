<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\User\Models\User;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    private RoleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RoleMiddleware();
    }

    public function test_admin_passes_viewer_requirement(): void
    {
        $request = $this->requestWithRole('admin');
        $passed  = false;

        $this->middleware->handle($request, function () use (&$passed) {
            $passed = true;
            return response()->json([]);
        }, 'viewer');

        $this->assertTrue($passed);
    }

    public function test_viewer_blocked_from_admin_route(): void
    {
        $request  = $this->requestWithRole('viewer');
        $response = $this->middleware->handle(
            $request,
            fn () => response()->json([]),
            'admin',
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $request  = new Request();
        $response = $this->middleware->handle(
            $request,
            fn () => response()->json([]),
            'viewer',
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    private function requestWithRole(string $role): Request
    {
        $user       = new User();
        $user->role = $role;

        $request = new Request();
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
