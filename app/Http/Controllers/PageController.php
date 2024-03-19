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
        return view('construccion');
    }
    public function impresos() {
        return view('construccion');
    }
    public function foto() {
        return view('construccion');
    }
    public function multimedia() {
        return view('construccion');
    }
    public function alianzas() {
        return view('construccion');
    }
    public function construccion() {
        return view('construccion');
    }
}
