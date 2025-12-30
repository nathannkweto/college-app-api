<!DOCTYPE html>
<html>
<head>
    <title>Welcome to the College Portal</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
<h2>Welcome, {{ $name }}!</h2>

<p>You have been successfully registered as a <strong>{{ ucfirst($role) }}</strong> at MATEM.</p>

<p>Here are your login credentials:</p>

<div style="background: #f4f4f4; padding: 15px; border-radius: 5px; display: inline-block;">
    <p><strong>{{ $role == 'student' ? 'Student ID' : 'Staff ID' }}:</strong> {{ $generatedId }}</p>
    <p>Log in to the portal with this <strong>Password:</strong> {{ $generatedId }}</p>
</div>

<p>Best Regards,<br>MATEM Administration</p>
</body>
</html>
