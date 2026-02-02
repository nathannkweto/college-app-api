<?php
// scripts/init_admin.php

use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Define your admins here
$adminsToCreate = [
    [
        'name' => 'Misheck Singowa',
        'email' => 'singowamisheck@gmail.com',
        'password' => 'Matem26Admin@Misheck',
    ],
    [
        'name' => 'Brenda Mulampa',
        'email' => 'brendamulampa30@gmail.com',
        'password' => 'Admin$Brenda$Matem$26',
    ],
    [
        'name' => 'Jimmy Tembo',
        'email' => 'jimmytembo6@gmail.com',
        'password' => 'JimmyTembo@MatemAdmin26',
    ],
];

foreach ($adminsToCreate as $adminData) {
    // 1. Create or Find the User
    $user = User::firstOrCreate(
        ['email' => $adminData['email']],
        [
            'name' => $adminData['name'],
            'password' => Hash::make($adminData['password']),
            'role' => 'admin',
        ]
    );

    // 2. Link to the Admin table if not already present
    Admin::firstOrCreate(['user_id' => $user->id]);

    echo "Successfully verified/created admin: " . $user->email . "\n";
}
