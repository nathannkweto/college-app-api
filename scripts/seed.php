<?php
// scripts/init_admin.php

use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

// Define your admins here
$adminsToCreate = [
    [
        'name' => 'Nathan Nkweto',
        'email' => 'nathannkweto@nkweto.tech',
        'password' => 'CollegeApp2025Test',
    ],
    [
        'name' => 'Mutale Kapini',
        'email' => 'mutale.kapini@ump.ac.ma',
        'password' => 'CollegeApp2025Test',
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
