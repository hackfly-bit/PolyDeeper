<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Contracts\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        return view('dashboard.positions', [
            'pageTitle' => 'Positions',
            'positions' => Position::query()->latest()->paginate(15),
        ]);
    }
}
