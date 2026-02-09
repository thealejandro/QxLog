<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('admins see admin shortcuts on dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Procedimientos');
    $response->assertSee('Pagos');
    $response->assertSee('Ajustar precios y configuraciones.'); // Unique to Admin card
});

test('instrumentists see instrumentist shortcuts on dashboard', function () {
    $user = User::factory()->create(['role' => 'instrumentist']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Mis Pagos');
    $response->assertSee('Ver mi historial de pagos recibidos.'); // Unique to Instrumentist card
    $response->assertSee('Mi Perfil');
    $response->assertDontSee('Ajustar precios y configuraciones.'); // Should not see Admin card
});

test('other users see default shortcuts on dashboard', function () {
    $user = User::factory()->create(['role' => 'doctor']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Mi Perfil');
    $response->assertDontSee('Ajustar precios y configuraciones.');
    $response->assertDontSee('Ver mi historial de pagos recibidos.');
});