<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Quantum Tic-Tac-Toe');
    }

    public function test_game_page_loads(): void
    {
        $response = $this->get('/game');

        $response->assertStatus(200);
        $response->assertSee('Quantum Tic-Tac-Toe');
    }
}
