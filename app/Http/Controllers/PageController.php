<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index() {
        return view('index');
    }

    public function servicios () {
        return view ('servicios');
    }

    public function marketing() {
        return view('marketing');
    }
}
