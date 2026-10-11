<?php

namespace App\Http\Controllers;

use App\Models\SaasApp;
use App\Services\Builder\StarterAppGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GenerateController extends Controller
{
    public function __invoke(SaasApp $app, StarterAppGenerator $generator): BinaryFileResponse
    {
        $zip = $generator->build($app);

        return response()->download($zip, basename($zip));
    }
}
