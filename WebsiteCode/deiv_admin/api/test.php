<?php
// test.php - API testing page
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Test</title>
</head>
<body>
    <h1>API Test Page</h1>
    
    <h2>Check Registration Status</h2>
    <form id="checkForm">
        <input type="text" name="username" placeholder="Username">
        <input type="text" name="email" placeholder="Email">
        <button type="button" onclick="checkRegistration()">Check</button>
    </form>
    
    <h2>Register New User (JSON)</h2>
    <textarea id="jsonData" rows="10" cols="50">
{
    "username": "Nad123",
    "password": "Nad123",
    "email": "Nad@gmail.com",
    "first_name": "Farah",
    "last_name": "Nadhirah",
    "role": "Law agencies",
    "organization": "TEST"
}
    </textarea>
    <br>
    <button onclick="registerUser()">Register</button>
    
    <div id="result"></div>
    
    <script>
    async function checkRegistration() {
        const form = document.getElementById('checkForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        
        const response = await fetch('check_registration.php?' + params);
        const data = await response.json();
        
        document.getElementById('result').innerHTML = 
            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    }
    
    async function registerUser() {
        const jsonData = document.getElementById('jsonData').value;
        
        const response = await fetch('register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: jsonData
        });
        
        const data = await response.json();
        document.getElementById('result').innerHTML = 
            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    }
    </script>
</body>
</html>