<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'user',
    ]);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'role' => 'admin',
    ]);
});

// Registration Tests
test('user can register with valid data', function () {
    $registrationData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/v1/register', $registrationData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'role', 'created_at'],
                'token',
            ],
        ])
        ->assertJsonFragment([
            'message' => 'User registered successfully',
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'user', // Should default to user role
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'role' => 'user',
    ]);

    // Verify token is returned and is valid
    $token = $response->json('data.token');
    expect($token)->not->toBeNull();
    expect($token)->toBeString();
});

test('registration fails with invalid data', function () {
    $this->postJson('/api/v1/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);

    $this->postJson('/api/v1/register', [
        'name' => 'A', // Too short
        'email' => 'invalid-email',
        'password' => '123', // Too short
        'password_confirmation' => 'different',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('registration fails with duplicate email', function () {
    $registrationData = [
        'name' => 'Another User',
        'email' => $this->user->email, // Existing email
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->postJson('/api/v1/register', $registrationData)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails when password confirmation does not match', function () {
    $registrationData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different_password',
    ];

    $this->postJson('/api/v1/register', $registrationData)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('new registration always gets user role regardless of role parameter', function () {
    $registrationData = [
        'name' => 'Wannabe Admin',
        'email' => 'wannabe@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin', // Should be ignored
    ];

    $response = $this->postJson('/api/v1/register', $registrationData);

    $response->assertStatus(201)
        ->assertJsonFragment(['role' => 'user']);

    $this->assertDatabaseHas('users', [
        'email' => 'wannabe@example.com',
        'role' => 'user', // Should be user, not admin
    ]);
});

// Login Tests
test('user can login with valid credentials', function () {
    $loginData = [
        'email' => $this->user->email,
        'password' => 'password', // Default factory password
    ];

    $response = $this->postJson('/api/v1/login', $loginData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ],
        ])
        ->assertJsonFragment([
            'message' => 'Login successful',
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->user->role,
        ]);

    // Verify token is returned
    $token = $response->json('data.token');
    expect($token)->not->toBeNull();
    expect($token)->toBeString();
});

test('login fails with invalid credentials', function () {
    $this->postJson('/api/v1/login', [
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonFragment([
            'email' => ['The provided credentials are incorrect.'],
        ]);
});

test('login fails with invalid email format', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'invalid-email',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails with missing fields', function () {
    $this->postJson('/api/v1/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);

    $this->postJson('/api/v1/login', ['email' => $this->user->email])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->postJson('/api/v1/login', ['password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails with non-existent email', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// Get Authenticated User (me) Tests
test('authenticated user can get their profile', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'role', 'created_at'],
            ],
        ])
        ->assertJsonFragment([
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->user->role,
        ]);
});

test('admin user can get their profile', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $this->admin->id,
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'admin',
        ]);
});

test('unauthenticated user cannot access me endpoint', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401);
});

test('user with invalid token cannot access me endpoint', function () {
    $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

// Logout Tests

test('unauthenticated user cannot logout', function () {
    $this->postJson('/api/v1/logout')
        ->assertStatus(401);
});

test('user with invalid token cannot logout', function () {
    $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
        ->postJson('/api/v1/logout')
        ->assertStatus(401);
});
