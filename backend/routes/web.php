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
Route::get('/create-user', function() {
    $user = new App\Models\User();
    $user->name = 'Events';
    $user->email = 'events@example.com';
    $user->password = Hash::make('admin@example.com');
    $user->email_verified_at = now();
    $user->save();
    return "User created with ID: " . $user->id;
});

Route::get('/', function () {
    return view('welcome');
});
