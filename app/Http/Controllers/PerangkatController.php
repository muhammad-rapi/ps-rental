<?php

namespace App\Http\Controllers;

use App\Models\Perangkat;
use Illuminate\Http\Request;

class PerangkatController extends Controller
{
    public function index()
    {
        $perangkats = Perangkat::all();
        return view('perangkats.index', compact('perangkats'));
    }
}
