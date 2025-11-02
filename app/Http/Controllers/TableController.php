<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Table;
use App\Models\User;
use App\Models\Role;


class TableController extends Controller
{
    // List all tables with QR links
    public function index()
    {
        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:5173');
        $tables = Table::with('user')->get()->map(function ($table) use ($frontendUrl) {
            $table->qr_link = $frontendUrl . "/customer?slug=" . $table->slug;
            return $table;
        });
        return response()->json($tables);
    }

    // Show a single table with QR link
    public function show($id)
    {
        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:5173');
        $table = Table::with('user')->findOrFail($id);
        $table->qr_link = $frontendUrl . "/customer?slug=" . $table->slug;

        // Optionally return a QR code image:
        // $table->qr_image = QrCode::format('png')->size(250)->generate($table->qr_link);

        return response()->json($table);
    }

    // Create new table (assign user_id for table user)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:tables',
            'slug' => 'required|string|unique:tables',
            'user_id' => 'required|exists:users,id',
        ]);

        // Ensure user has Table role
        $tableRoleId = Role::where('name', 'Table')->value('id');
        $isTableUser = User::where('id', $request->user_id)
            ->where('role_id', $tableRoleId)
            ->exists();

        if (!$isTableUser) {
            return response()->json(['message' => 'User must have the Table role.'], 422);
        }

        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:5173');
        $table = Table::create($request->only('name', 'slug', 'user_id'));
        $table->qr_link = $frontendUrl . "/customer?slug=" . $table->slug;
        return response()->json($table, 201);
    }

    // Update table info
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|unique:tables,name,' . $id,
            'slug' => 'sometimes|required|string|unique:tables,slug,' . $id,
            'user_id' => 'required|exists:users,id',
        ]);

        // Ensure user has Table role
        $tableRoleId = Role::where('name', 'Table')->value('id');
        $isTableUser = User::where('id', $request->user_id)
            ->where('role_id', $tableRoleId)
            ->exists();

        if (!$isTableUser) {
            return response()->json(['message' => 'User must have the Table role.'], 422);
        }

        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:5173');
        $table = Table::findOrFail($id);
        $table->update($request->only('name', 'slug', 'user_id'));
        $table->qr_link = $frontendUrl . "/customer?slug=" . $table->slug;
        return response()->json($table);
    }

    // Delete a table
    public function destroy($id)
    {
        Table::findOrFail($id)->delete();
        return response()->json(['message' => 'Table deleted']);
    }
}
