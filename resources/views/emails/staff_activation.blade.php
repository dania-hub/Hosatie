<!DOCTYPE html>
<html>
<head><title>Activate Account</title></head>
<body>
    <h2>Welcome, {{ $name }}</h2>
    <p>Your account has been created at Hosatie.</p>
    <p>Please click the link below to set your password and activate your account:</p>
    <a href="{{ $url }}">{{ $url }}</a>
    <p>This link expires in 24 hours.</p>
</body>
</html>

