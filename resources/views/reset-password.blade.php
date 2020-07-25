<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        function validateForm() {
            var form = document.forms["resetPassword"];
            var password = form["password"].value;
            var confirm = form["confirm"].value;
            if (!legalPassword(password)) {
                alert('Password is not ok!');
                return false;
            }
            if (password !== confirm) {
                alert("Password and Confirm Password fields does not match!");
                return false;
            }
        }

        function legalPassword(password) {
            if (password.length < 4) {
                return false;
            }
            return true;
        }
    </script>
    <style>
        body {font-family: Arial, Helvetica, sans-serif;}
        * {box-sizing: border-box;}

        input[type=text], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            margin-top: 6px;
            margin-bottom: 16px;
            resize: vertical;
        }

        input[type=submit] {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type=submit]:hover {
            background-color: #45a049;
        }

        .container {
            border-radius: 5px;
            background-color: #f2f2f2;
            padding: 20px;
        }
    </style>
</head>
<body>

<h3>Reset Password</h3>

<div class="container">
    <form action="/password/reset/{{ $token }}" method="post" name="resetPassword" onsubmit="return validateForm()">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" />

        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" />

        <input type="submit" value="Submit">
    </form>
</div>

</body>
</html>
