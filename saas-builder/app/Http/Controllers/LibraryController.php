<?php

namespace App\Http\Controllers;

use App\Models\Constitution;
use App\Models\Law;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(): View
    {
        return view('library', [
            'constitutions' => Constitution::orderBy('code')->get(),
            'laws' => Law::orderBy('category')->orderBy('code')->get()->groupBy('category'),
            'categories' => config('playbook.law_categories'),
        ]);
    }
}
