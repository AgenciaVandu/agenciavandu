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

        $request->validate([
            'name' => 'required',
            'lastname' => 'required',
            'tel' => 'required',
            'email' => 'required'

        ]);

        Mail::to('ab@agenciavandu.com')
         ->send(new ContactanosMailable($request->all()));
         
         return redirect()->route('home')
                ->with('info', 'Mensaje enviado');
    }
}
