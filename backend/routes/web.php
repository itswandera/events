<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// Temporary route - REMOVE AFTER USE
Route::get('/create-admin', function() {
    try {
        $user = new App\Models\User();
        $user->name = 'Admin User';
        $user->email = 'admin@example.com';
        $user->password = Hash::make('your_secure_password');
        $user->email_verified_at = now();
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/list-users', function() {
    $users = App\Models\User::all();
    return response()->json([
        'count' => $users->count(),
        'users' => $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at
            ];
        })
    ]);
});


Route::get('/', function () {
    return view('welcome');
});
