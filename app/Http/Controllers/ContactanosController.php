<?php

namespace App\Http\Controllers;

use App\Mail\ContactanosMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class ContactanosController extends Controller
{
    public function index() {
        return view('contacto');
    }

    public function store(Request $request) {
        Mail::to('ab@agenciavandu.com')
         ->send(new ContactanosMailable($request->all()));

         return ('mensaje enviado');
    }
}
