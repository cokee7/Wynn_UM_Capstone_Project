        <!-- Page content ends here -->
        </main>
    </div> <!-- .admin-container -->

    <script>
        // Add simple confirm dialog for delete buttons
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        event.preventDefault(); // Stop form submission if user cancels
                    }
                });
            });
        });
    </script>
    <!-- Add other global JS scripts here -->
</body>
</html>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // Try only if still open
    try {
        if (@$conn->ping()) {
            $conn->close();
        }
    } catch (Exception $e) {
        // Avoid fatal if ping fails
    }
}
?>
