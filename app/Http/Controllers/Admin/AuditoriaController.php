<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(): View
    {
        return view('admin.auditoria', [
            'registros' => Auditoria::with('usuario:id,name,email')->latest('id')->paginate(50),
        ]);
    }
}
