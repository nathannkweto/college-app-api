<?php

namespace Tests\Feature\Api\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spectator\Spectator;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['spectator.sources.local.base_path' => base_path('specs')]);
        config(['spectator.path_prefix' => 'api']);

        $specPath = base_path('specs/core.yaml');

        if (!file_exists($specPath)) {
            dd("STOP! The file was not found at: " . $specPath . ". Please check the folder name and file name.");
        }

        Spectator::using('core.yaml');
    }
    public function test_login_successfully_matches_spec()
    {
        $user = User::factory()->create([
            'email' => 'test@college.edu',
            'password' => Hash::make('password'),
            'role' => 'STUDENT',
        ]);

        // Act: Make the request
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@college.edu',
            'password' => 'password',
        ]);
        // Assert:
        // 1. Check if the REQUEST payload matched the spec validation
        $response->assertValidRequest()
            // 2. Check if the RESPONSE matched the 200 schema in YAML
            ->assertValidResponse(200);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@college.edu',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }
}
