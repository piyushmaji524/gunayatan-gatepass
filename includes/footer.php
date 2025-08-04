    </div>
      <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 text-center text-md-start">
                    <p>&copy; <?php echo date('Y'); ?> Gunayatan Gatepass System</p>
                </div>                <div class="col-md-4 text-center">
                    <?php
                    // Determine the correct copyright link based on current location
                    $current_path = $_SERVER['PHP_SELF'];
                    $copyright_link = '';
                    
                    if (strpos($current_path, '/admin/') !== false) {
                        $copyright_link = '../copyright.php?from=admin';
                    } elseif (strpos($current_path, '/security/') !== false) {
                        $copyright_link = '../copyright.php?from=security';
                    } elseif (strpos($current_path, '/user/') !== false) {
                        $copyright_link = '../copyright.php?from=user';
                    } else {
                        $copyright_link = 'copyright.php';
                    }
                    ?>
                    <p>Developed by <a href="<?php echo $copyright_link; ?>" class="text-decoration-none developer-credit">Piyush Maji</a></p>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <p>Version 1.15.2</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/script.js"></script>
    <!-- Additional JS if needed -->
    <?php if (isset($additional_js)) echo $additional_js; ?>
    
    <!-- PWA Install Prompt Script -->
    <script>
        // Ensure PWA install prompt is shown on every page
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.pwaInstallManager && !window.pwaInstallManager.isInstalled) {
                    window.pwaInstallManager.checkAndShowPrompt();
                }
            }, 2000); // Show after 2 seconds on all pages
        });
    </script>
</body>
</html>
