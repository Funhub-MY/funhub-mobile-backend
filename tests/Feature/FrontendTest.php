<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testSupposeFrontendRedirectToAdmin()
    {
        $response = $this->get('/'); 

        $response->assertStatus(302);
    }
}
