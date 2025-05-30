<?php
include_once 'utils\config.php';

// Debugging lines:
if (!isset($conn)) {
    die("Connection variable \$conn is not set.");
}

if ($conn === false) {
    die("Database connection failed.");
}

// Query to get services with provider info
$sql = "
SELECT 
    s.service_id,
    s.title,
    s.description,
    s.price,
    s.average_rating,
    u.first_name + ' ' + u.last_name AS provider_name,
    u.email,
    u.user_id,
    si.image_path
FROM Services s
JOIN Users u ON s.provider_id = u.user_id
LEFT JOIN (
    SELECT service_id, image_path 
    FROM ServiceImages 
    WHERE is_primary = 1
) si ON s.service_id = si.service_id
WHERE s.is_active = 1
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Query failed:<br><pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}
// Loop through each service
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $serviceId = $row['service_id'];
    $title = htmlspecialchars($row['title']);
    $description = htmlspecialchars($row['description']);
    $price = number_format($row['price'], 2);
    $rawPrice = $row['price'];
    $rating = $row['average_rating'];
    $provider = htmlspecialchars($row['provider_name']);
    $email = htmlspecialchars($row['email']);

    // Fetch all images
    $imageSql = "SELECT image_path FROM ServiceImages WHERE service_id = ?";
    $imageStmt = sqlsrv_query($conn, $imageSql, [$serviceId]);
    $images = [];
    while ($imgRow = sqlsrv_fetch_array($imageStmt, SQLSRV_FETCH_ASSOC)) {
        $images[] = $imgRow['image_path'];
    }
    if (empty($images)) {
        $images[] = 'assets/images/services/placeholder.jpg';
    }
    $dataImages = htmlspecialchars(implode(',', $images));

    // Get categories
    $categorySql = "
        SELECT c.name 
        FROM ServiceCategoryLink scl 
        JOIN Categories c ON scl.category_id = c.category_id
        WHERE scl.service_id = ?
    ";
    $catStmt = sqlsrv_query($conn, $categorySql, [$serviceId]);
    $categories = [];
    while ($catRow = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) {
        $categories[] = $catRow['name'];
    }
    $categoryList = htmlspecialchars(implode(', ', $categories));
    $ratingStars = str_repeat("★", floor($rating)) . (fmod($rating, 1) >= 0.5 ? "☆" : "");

    echo <<<HTML
    <div class="product-card">
        <img src="{$images[0]}" alt="{$title}">
        <h3>{$title}</h3>
        <p>₱{$price}</p>
        <div class="check-out">
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#serviceModal" 
                data-images="{$dataImages}" 
                data-title="{$title}" 
                data-price="{$rawPrice}" 
                data-review="{$ratingStars} ({$rating})"
                data-categories="{$categoryList}" 
                data-description="{$description}" 
                data-provider="{$provider}, {$email}">
                View Details
            </button>
            <a href="billing.html?product={$title}&price={$rawPrice}&qty=1" class="btn btn-primary">Book Now</a>
        </div>
    </div>
HTML;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
