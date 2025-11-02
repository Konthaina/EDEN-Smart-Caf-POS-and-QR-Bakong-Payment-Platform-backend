<?php
// app/Http/Controllers/TableLoginController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Table;
use Illuminate\Support\Facades\Auth;

class TableLoginController extends Controller
{
    public function loginBySlug(Request $request)
    {
        $request->validate(['table_slug' => 'required|string']);

        $table = Table::where('slug', $request->table_slug)->with('user')->first();

        if (!$table || !$table->user) {
            return response()->json(['message' => 'Table or Table User not found'], 404);
        }

        // Issue a Sanctum token with limited ability (optional: use abilities for more control)
        $token = $table->user->createToken(
            'table-guest-order',
            ['guest-order'] // custom ability
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'table' => [
                'id' => $table->id,
                'name' => $table->name,
                'slug' => $table->slug
            ]
        ]);
    }
}
