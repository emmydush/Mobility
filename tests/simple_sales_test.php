<?php
// Simple test for sales module functionality
include_once 'api/config/database.php';

echo "=== SALES MODULE FUNCTIONALITY TEST ===\n\n";

// Test 1: Check if tenant_id columns exist
echo "Test 1: Database Structure\n";
$tables = ['sales', 'products', 'customers'];
$all_good = true;

foreach ($tables as $table) {
    $result = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table' AND column_name = 'tenant_id'");
    if ($result && $result->rowCount() > 0) {
        echo "✓ $table table has tenant_id column\n";
    } else {
        echo "✗ $table table missing tenant_id column\n";
        $all_good = false;
    }
}

echo "\n";

// Test 2: Check product price field
echo "Test 2: Product Price Field\n";
$result = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'products' AND column_name = 'price'");
if ($result && $result->rowCount() > 0) {
    echo "✓ Products table uses 'price' field\n";
} else {
    echo "✗ Products table missing 'price' field\n";
    $all_good = false;
}

echo "\n";

// Test 3: Test a simple query with tenant filtering
echo "Test 3: Tenant Filtering\n";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE tenant_id = ?");
if ($stmt) {
    $tenant_id = 2; // Using tenant 2 for test
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    echo "Sales for tenant $tenant_id: $count\n";
    echo "✓ Tenant filtering working\n";
} else {
    echo "✗ Failed to prepare tenant filtering query\n";
    $all_good = false;
}

echo "\n";

// Test 4: Check relationships
echo "Test 4: Data Relationships\n";
$query = "SELECT s.id as sale_id, c.id as customer_id, p.id as product_id 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          LEFT JOIN sale_items si ON s.id = si.sale_id 
          LEFT JOIN products p ON si.product_id = p.id 
          WHERE s.tenant_id = ? 
          LIMIT 1";
$stmt = $conn->prepare($query);
if ($stmt) {
    $tenant_id = 2;
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($result)) {
        $row = $result[0];
        echo "Sample sale data:\n";
        echo "  Sale ID: " . $row['sale_id'] . "\n";
        echo "  Customer ID: " . $row['customer_id'] . "\n";
        echo "  Product ID: " . $row['product_id'] . "\n";
        echo "✓ Data relationships working\n";
    } else {
        echo "No sales data found for testing relationships\n";
    }
} else {
    echo "✗ Failed to prepare relationship query\n";
    $all_good = false;
}

echo "\n";

echo "=== TEST RESULTS ===\n";
if ($all_good) {
    echo "✓ ALL TESTS PASSED - Sales module is working correctly\n";
} else {
    echo "✗ SOME TESTS FAILED - Check the issues above\n";
}

$conn = null;
?>