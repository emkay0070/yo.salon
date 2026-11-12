<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Profession;

class ProfessionController extends Controller
{
    public function index()
    {
        $professions = Profession::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['craftTaxonomy' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get();

        return response()->json($professions);
    }
}
