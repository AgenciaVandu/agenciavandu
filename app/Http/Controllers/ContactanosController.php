<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactanosController extends Controller
{
    public function index() {
        return view('contacto');
    }

    public function store(Request $request) {
         return $request ->all();
    }
}
