<?php

namespace App\Http\Controllers;

class GameController extends Controller
{
    public function home()
    {
        return view('home');
    }

    public function game()
    {
        return view('game');
    }
}
