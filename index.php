<?php
session_start();
include "db.php";

$result = $conn->query("SELECT * FROM properties WHERE status='approved'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nestoida</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

<!-- Navbar -->
<div class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">
        <h1 class="text-xl font-semibold">Nestoida</h1>
        <a href="submit-property.php" class="text-sm bg-black text-white px-4 py-2 rounded">
            List Your PG
        </a>
    </div>
</div>

<!-- Search -->
<div class="max-w-6xl mx-auto px-6 mt-8">
    <form method="GET" class="flex gap-3">
        <input type="text" name="sector"
               placeholder="Search by sector..."
               class="flex-1 border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-black">
        <button class="bg-black text-white px-6 py-2 rounded">
            Search
        </button>
    </form>
</div>

<!-- Grid -->
<div class="max-w-6xl mx-auto px-6 mt-10 grid md:grid-cols-3 gap-8">

<?php while($row = $result->fetch_assoc()) { ?>
    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition">
        <img src="uploads/<?php echo $row['image']; ?>"
             class="w-full h-56 object-cover rounded-t-xl">

        <div class="p-5">
            <h2 class="text-lg font-semibold">
                <?php echo $row['title']; ?>
            </h2>

            <p class="text-gray-600 mt-1">
                Sector <?php echo $row['sector']; ?>
            </p>

            <p class="text-black font-medium mt-2">
                ₹<?php echo $row['rent']; ?> / month
            </p>

            <a href="property.php?id=<?php echo $row['id']; ?>"
               class="inline-block mt-4 text-sm bg-black text-white px-4 py-2 rounded">
               View Details
            </a>
        </div>
    </div>
<?php } ?>

</div>

</body>
</html>