<?php

namespace App\Http\Controllers;

use App\Models\SaasApp;
use App\Services\Builder\NewAppFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppController extends Controller
{
    public function index(): View
    {
        return view('apps.index', ['apps' => SaasApp::latest()->get()]);
    }

    public function create(): View
    {
        return view('apps.create', ['templates' => config('playbook.templates')]);
    }

    public function store(Request $request, NewAppFactory $factory): RedirectResponse
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $basics = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'required|alpha_dash|max:60|unique:saas_apps,slug',
            'summary' => 'nullable|string|max:255',
            'template' => ['required', Rule::in(array_keys(config('playbook.templates')))],
        ]);

        $app = $factory->create($basics);

        return redirect()->route('wizard.show', [$app, 'constitutions'])
            ->with('status', "{$app->name} is started with the core constitutions, laws and ACFCM items ticked.");
    }

    public function destroy(SaasApp $app): RedirectResponse
    {
        $app->delete();

        return redirect()->route('apps.index')->with('status', "{$app->name} was deleted.");
    }
}
