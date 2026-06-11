<?php
include 'config.php';
$username = '28';
$password_input = '123456';
$password_hash = md5($password_input);
$query = "SELECT * FROM users WHERE username='$username' AND password='$password_hash'";
$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) > 0){
    echo "Login berhasil untuk username $username";
} else {
    echo "Login gagal. Cek data user:<br>";
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    if(mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_assoc($check);
        echo "Username ditemukan. Password di database: " . $row['password'] . "<br>";
        echo "Password yang diinput setelah MD5: $password_hash<br>";
        echo "Apakah sama? " . ($row['password'] == $password_hash ? "YA" : "TIDAK");
    } else {
        echo "Username tidak ditemukan";
    }
}
?>