<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
?>

<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = $_POST['title'];
    $type = $_POST['type'];
    $rent = $_POST['rent'];
    $sector = $_POST['sector'];
    $description = $_POST['description'];
    $amenities = $_POST['amenities'];
    $phone = $_POST['phone'];

    $imageName = $_FILES['image']['name'];
    $tempName = $_FILES['image']['tmp_name'];
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($_FILES['image']['type'], $allowedTypes)) {
    die("Only JPG, JPEG, PNG allowed.");
}

    move_uploaded_file($tempName, "uploads/" . $imageName);

    $sql = "INSERT INTO properties 
    (title, type, rent, sector, description, amenities, image, phone) 
    VALUES 
    ('$title', '$type', '$rent', '$sector', '$description', '$amenities', '$imageName', '$phone')";

    $conn->query($sql);

    echo "Property Added Successfully!";
}
?>
<a href="logout.php">Logout</a>
<h2>Add Property</h2>

<form method="POST" enctype="multipart/form-data">
    Title: <input type="text" name="title" required><br><br>
    Type: <input type="text" name="type" required><br><br>
    Rent: <input type="number" name="rent" required><br><br>
    Sector: <input type="text" name="sector" required><br><br>
    Description: <textarea name="description"></textarea><br><br>
    Amenities: <textarea name="amenities"></textarea><br><br>
    Phone: <input type="text" name="phone" required><br><br>
    Image: <input type="file" name="image" required><br><br>

    <button type="submit">Add Property</button>
</form>