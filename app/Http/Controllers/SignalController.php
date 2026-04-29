<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use Illuminate\Contracts\View\View;

class SignalController extends Controller
{
    public function index(): View
    {
        return view('dashboard.signals', [
            'pageTitle' => 'Signals',
            'signals' => Signal::query()
                ->with('wallet:id,address,weight')
                ->latest()
                ->paginate(20),
        ]);
    }
}
