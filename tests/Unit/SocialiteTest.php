<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;

class SocialiteTest extends TestCase
{

    // protected function setUp(): void
    // {
    //     parent::setUp();
    // }

    // /**
    //  * Login in by Facebook.
    //  * Expected results will be users able to login and facebook_id will be populated in DB.
    //  *
    //  * @return void
    //  */
    // public function testLoginInThroughFacebook()
    // {
    //     $response = $this->postJson('/api/v1/login/facebook');

    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'user',
    //             'token'
    //         ]);
    //     // get id from responses.
    //     $user_id = $response->json('user.id');
    //     // get if database has this user_id, as it might be a newly created or existing user.
    //     $this->assertDatabaseHas('users', ['id' => $user_id]);
    //     // if above passed, grab the user.
    //     $user = \App\Models\User::where('id', $user_id)->first();
    //     // check if facebook_id has been populated.
    //     $this->assertNotNull($user->facebook_id);
    //     // check if token exists.
    //     $token = $response->json('token');
    //     // after create token, it will return as an array
    //     $this->assertIsArray($token);
    // }
//     /**
//      * Login in by Google.
//      * Expected results will be users able to login and google_id will be populated in DB.
//      *
//      * @return void
//      */
//     public function testLoginInThroughGoogle()
//     {
//         $response = $this->postJson('/api/v1/login/google');
//
//         $response->assertStatus(200)
//             ->assertJsonStructure([
//                 'user',
//                 'token'
//             ]);
//         // get id from responses.
//         $user_id = $response->json('user.id');
//         // get if database has this user_id, as it might be a newly created or existing user.
//         $this->assertDatabaseHas('users', ['id' => $user_id]);
//         // if above passed, grab the user.
//         $user = \App\Models\User::where('id', $user_id)->first();
//         // check if facebook_id has been populated.
//         $this->assertNotNull($user->google_id);
//         // check if token exists.
//         $token = $response->json('token');
//         // after create token, it will return as an array
//         $this->assertIsArray($token);
//     }
//    /**
//     * Login in by Social.
//     * Expected results will be users able to login then google_id and facebook_id will be populated in DB.
//     *
//     * @return void
//     */
//    public function testSocialLogin()
//    {
//        $response = $this->postJson('/api/v1/login/social');
//
//        $response->assertStatus(200)
//                ->assertJsonStructure([
//                    'user',
//                    'token'
//                ]);
//        // get id from responses.
//        $user_id = $response->json('user.id');
//        // get if database has this user_id, as it might be a newly created or existing user.
//        $this->assertDatabaseHas('users', ['id' => $user_id]);
//        // if above passed, grab the user.
//        $user = \App\Models\User::where('id', $user_id)->first();
//        // check if facebook_id has been populated.
//        $this->assertNotNull($user->google_id);
//        $this->assertNotNull($user->facebook_id);
//        // check if token exists.
//        $token = $response->json('token');
//        // after create token, it will return as an array
//        $this->assertIsArray($token);
//    }
}
