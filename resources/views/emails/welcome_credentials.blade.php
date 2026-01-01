<!DOCTYPE html>
<html>
<head>
    <title>Welcome to MATEM College of Education</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
<h2>Welcome, {{ $name }}!</h2>

<p>You have been registered as a <strong>{{ ucfirst($role) }}</strong> at MATEM.</p>

<div style="background: #f4f4f4; padding: 15px; border-radius: 5px; display: inline-block;">
    <p><strong>{{ $role == 'student' ? 'Student ID' : 'Staff ID' }}:</strong> {{ $generatedId }}</p>
</div>

<p>Use your email and ID-as-password to login to the portal</p>

<p>Best Regards,<br>MATEM Administration</p>
</body>
</html>
