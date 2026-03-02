<?php
include "db.php";
$id = $_GET['id'];

$result = $conn->query("SELECT * FROM properties WHERE id=$id");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $row['title']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
</head>
<body>

<div class="navbar">
    <a href="index.php" style="color:white;text-decoration:none;">
        ← Back to Listings
    </a>
</div>

<div class="container">

    <div class="card">
        <img src="uploads/<?php echo $row['image']; ?>" style="height:350px;">
        <div class="card-body">

            <div class="card-title" style="font-size:24px;">
                <?php echo $row['title']; ?>
            </div>

            <div class="price" style="font-size:20px;">
                ₹<?php echo $row['rent']; ?> / month
            </div>

            <p><strong>Sector:</strong> <?php echo $row['sector']; ?></p>

            <p><strong>Description:</strong><br>
                <?php echo $row['description']; ?>
            </p>

            <p><strong>Amenities:</strong><br>
                <?php echo $row['amenities']; ?>
            </p>

            <a class="button" href="tel:<?php echo $row['phone']; ?>">
                📞 Call Owner
            </a>

        </div>
    </div>

</div>

</body>
</html>