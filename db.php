<?php
$conn = new mysqli("localhost", "phpmyadmin", "Satyaminc.tk@9721", "nestoida");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>