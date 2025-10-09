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

// Debug route to test event creation
Route::get('/debug-event-create', function() {
    try {
        // Test with minimal required data
        $eventData = [
            'title' => 'Test Event ' . time(),
            'description' => 'Test description',
            'start_date' => now()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'organizer_id' => 1, // Your organizer ID
            'timezone' => 'UTC',
            'category_id' => 1, // Check if this is required
        ];

        $event = \App\Models\Event::create($eventData);
        
        return response()->json([
            'success' => true,
            'message' => 'Test event created',
            'event_id' => $event->id
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
Route::get('/debug-event-table', function() {
    try {
        $columns = \DB::select('DESCRIBE events');
        return response()->json([
            'columns' => $columns,
            'required_fields' => collect($columns)->where('Null', 'NO')->pluck('Field')
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
Route::get('/', function () {
    return view('welcome');
});
