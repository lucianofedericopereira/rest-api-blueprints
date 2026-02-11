<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    public function test_viewer_can_list_users(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        Sanctum::actingAs($viewer, ['viewer']);

        $this->getJson('/api/v1/users')->assertStatus(200);
    }

    public function test_viewer_cannot_create_user(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        Sanctum::actingAs($viewer, ['viewer']);

        $this->postJson('/api/v1/users', [
            'email'    => 'new@example.com',
            'password' => 'Str0ng!Pass12',
            'role'     => 'viewer',
        ])->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/v1/users', [
            'email'    => 'new@example.com',
            'password' => 'Str0ng!Pass12!',
            'role'     => 'viewer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'role', 'active', 'created_at']]);
    }

    public function test_create_user_validates_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/v1/users', [
            'email'    => 'not-an-email',
            'password' => 'weak',
            'role'     => 'superuser',  // invalid role
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id', 'details']]);
    }

    public function test_get_user_not_found_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_admin_can_delete_user(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);
        Sanctum::actingAs($admin, ['admin']);

        $this->deleteJson("/api/v1/users/{$target->id}")->assertStatus(204);

        // A.12: User should be soft-deleted, not hard-deleted
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }
}
