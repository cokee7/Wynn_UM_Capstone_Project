<?php
$pageTitle = "User Statistics";
require_once 'admin_header.php'; // Include header
require_once 'admin_db_connect.php'; // Include DB connection

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Fetch User Growth Data ---
$monthly_growth = [];
$sql_growth = "SELECT DATE_FORMAT(Add_Time, '%Y-%m') as month, COUNT(*) as users_added
               FROM user_file
               GROUP BY month
               ORDER BY month";
$result_growth = $conn->query($sql_growth);
if ($result_growth) {
    while ($row = $result_growth->fetch_assoc()) {
        $monthly_growth[] = $row;
    }
} else {
    // Optional: Add error handling if the query fails
    echo "Error fetching user growth data: " . $conn->error;
}

// --- Start HTML Output ---
?>

<h1><?php echo htmlspecialchars($pageTitle); ?></h1>

<?php
// --- Placeholder for OTHER statistics (if any) ---
// If you had other stats like "Total Users", "Active Users", etc.,
// you would put their HTML output here.
// Example:
// echo "<h2>Overall Summary</h2>";
// echo "<p>Total Registered Users: " . getTotalUsers($conn) . "</p>"; // Assuming getTotalUsers() exists
?>

<!-- Section: User Growth Over Time -->
<section class="user-growth-section" style="margin-top: 2rem; margin-bottom: 2rem;">

    <h2>User Growth Over Time</h2>
    <p>This chart shows the cumulative number of users registered over time.</p>
    <div style="max-width: 700px; margin-bottom: 2rem;">
        <canvas id="growthChart"></canvas>
    </div>

    <h3>Users Added Per Month</h3>
    <?php if (!empty($monthly_growth)): ?>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Users Added</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_growth as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['month']); ?></td>
                        <td><?php echo htmlspecialchars($row['users_added']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No monthly registration data available.</p>
    <?php endif; ?>

</section>
<!-- End Section: User Growth Over Time -->


<?php
// --- Placeholder for MORE statistics (if any) ---
// If you had other sections, they could go here.
?>


<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- JavaScript for Growth Chart -->
<script>
    // Ensure the DOM is ready before trying to get the context
    document.addEventListener('DOMContentLoaded', (event) => {
        const growthCtx = document.getElementById('growthChart');
        if (growthCtx) { // Check if the canvas element exists
             growthCtx.getContext('2d');
            const growthChart = new Chart(growthCtx, {
                type: 'line', // Line chart is good for cumulative growth
                data: {
                    labels: <?php echo json_encode(array_column($monthly_growth, 'month')); ?>,
                    datasets: [{
                        label: 'Cumulative Users',
                        data: (function() {
                            // Calculate cumulative counts directly in JS
                            const monthly = <?php echo json_encode(array_column($monthly_growth, 'users_added'), JSON_NUMERIC_CHECK); ?>;
                            let cumulative = [];
                            let total = 0;
                            for (let i = 0; i < monthly.length; i++) {
                                total += monthly[i]; // Already numbers due to JSON_NUMERIC_CHECK
                                cumulative.push(total);
                            }
                            return cumulative;
                        })(),
                        borderColor: '#0A74DA', // A nice blue color
                        backgroundColor: 'rgba(10, 116, 218, 0.2)', // Light blue fill
                        fill: true,         // Fill area under the line
                        tension: 0.1        // Slight curve to the line
                    }]
                },
                options: {
                    responsive: true, // Make chart responsive
                    maintainAspectRatio: true, // Maintain aspect ratio (can be false if needed)
                    scales: {
                        y: {
                            beginAtZero: true, // Start Y-axis at 0
                            title: {
                                display: true,
                                text: 'Total Users'
                            }
                        },
                        x: {
                             title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                     plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        },
                        title: {
                             display: true,
                             text: 'Cumulative User Growth by Month'
                        }
                    }
                }
            });
        } else {
            console.error("Canvas element with ID 'growthChart' not found.");
        }
    });
</script>

<?php
// --- Include Footer ---
require_once 'admin_footer.php';
?>