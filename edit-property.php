<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM properties WHERE id=$id");
$row = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $title = $_POST['title'];
    $type = $_POST['type'];
    $rent = $_POST['rent'];
    $sector = $_POST['sector'];
    $description = $_POST['description'];
    $amenities = $_POST['amenities'];
    $phone = $_POST['phone'];

    $stmt = $conn->prepare("UPDATE properties SET 
    title=?, 
    type=?, 
    rent=?, 
    sector=?, 
    description=?, 
    amenities=?, 
    phone=? 
    WHERE id=?");

$stmt->bind_param(
    "ssissssi",
    $title,
    $type,
    $rent,
    $sector,
    $description,
    $amenities,
    $phone,
    $id
);

$stmt->execute();
    header("Location: index.php");
    exit();
}
?>

<h2>Edit Property</h2>

<form method="POST">
    Title: <input type="text" name="title" value="<?php echo $row['title']; ?>"><br><br>
    Type: <input type="text" name="type" value="<?php echo $row['type']; ?>"><br><br>
    Rent: <input type="number" name="rent" value="<?php echo $row['rent']; ?>"><br><br>
    Sector: <input type="text" name="sector" value="<?php echo $row['sector']; ?>"><br><br>
    Description:<br>
    <textarea name="description"><?php echo $row['description']; ?></textarea><br><br>
    Amenities:<br>
    <textarea name="amenities"><?php echo $row['amenities']; ?></textarea><br><br>
    Phone: <input type="text" name="phone" value="<?php echo $row['phone']; ?>"><br><br>

    <button type="submit">Update Property</button>
</form>