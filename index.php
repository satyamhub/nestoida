<?php
session_start();
include "db.php";
$result = $conn->query("SELECT * FROM properties");
if(isset($_GET['sector']) && $_GET['sector'] != ""){

    $sector = "%" . $_GET['sector'] . "%";

    $stmt = $conn->prepare("SELECT * FROM properties WHERE sector LIKE ?");
    $stmt->bind_param("s", $sector);
    $stmt->execute();

    $result = $stmt->get_result();

} else {
    $result = $conn->query("SELECT * FROM properties");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nestoida</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="navbar">
    Nestoida – PG & Flats in Noida
    <form method="GET" style="margin-top:10px;">
    <input type="text" name="sector" placeholder="Search by sector..." style="padding:8px;">
    <button type="submit" style="padding:8px;">Search</button>
</form>
</div>

<div class="container">
    <div class="grid">

<?php while($row = $result->fetch_assoc()) { ?>
    <div class="card">
        <img src="uploads/<?php echo $row['image']; ?>">
        <div class="card-body">
            <div class="card-title"><?php echo $row['title']; ?></div>
            <div class="price">₹<?php echo $row['rent']; ?></div>
            <div>Sector <?php echo $row['sector']; ?></div>
            <a class="button" href="property.php?id=<?php echo $row['id']; ?>">View Details</a>
            <?php if(isset($_SESSION['admin'])) { ?>
    <a class="button" href="edit-property.php?id=<?php echo $row['id']; ?>" style="background:#16a34a;">Edit</a>
    <a class="button" href="delete-property.php?id=<?php echo $row['id']; ?>" 
       style="background:#dc2626;"
       onclick="return confirm('Are you sure?');">
       Delete
    </a>
<?php } ?>
        </div>
    </div>
<?php } ?>
</div>
</div>

</body>
</html>