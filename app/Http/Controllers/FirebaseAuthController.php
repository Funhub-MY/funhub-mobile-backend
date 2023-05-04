<?php

namespace App\Http\Controllers;

use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseAuthController extends Controller
{
    protected $auth;

    public function __construct()
    {
        $this->auth = Firebase::auth();
    }
}
