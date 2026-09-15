<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Username او Password دواړه ولیکئ.";

    } else {

        $sql = "SELECT * FROM users
                WHERE username = :username
                AND status = 'active'
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../admin/dashboard.php");
            exit;

        } 
        else {

            $error = "  ناسم دی Username یا Password  ";
        }
        
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bakery Management - Login</title>
    <!-- image link -->
     <link rel="stylesheet" href="../assets/css/backgrounds.css">
      <!-- bootstrap -->
<link
    rel="stylesheet"
    href="../assets/css/bootstrap.min.css">

    <!-- Bootstrap Icons -->

 <link
    rel="stylesheet"
    href="../assets/css/bootstrap-icons.css">


    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

         .login-box {
            width: 600px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            background-color: #8b7e6eff;
        }  

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        } 

         button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: #333;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #555;
        }

        .error {
            background: #ffdede;
            color: #b00000;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>

<body class="bg-login">
    
<div class="login-box">

    <h2>Bakery Management</h2>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
            class="form-control"
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
            class="form-control"
        >

        <button type="submit">
            Login
        </button>

    </form>

</div>



    <!-- javascript links  -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>