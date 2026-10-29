<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing');
    }
}