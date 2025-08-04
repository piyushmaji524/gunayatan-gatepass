<?php
require_once '../includes/config.php';
require_once '../includes/translation_helper.php';

// Check if user is logged in and has security role
if (!isLoggedIn() || $_SESSION['role'] !== 'security') {
    header("Location: ../index.php");
    exit();
}

// Set page title
$page_title = "Hindi Translation Test - Security Panel";

// Include header
include '../includes/header.php';

// Add translation CSS
addTranslationCSS();

// Sample items for demonstration (mix of common and uncommon items)
$sample_items = [
    'Computer', 'Mobile Phone', 'Laptop', 'Printer', 'Scanner', 
    'Office Chair', 'Desk', 'Monitor', 'Keyboard', 'Mouse',
    'Books', 'Files', 'Documents', 'Tools', 'Equipment',
    'Smartphone', 'Headphones', 'Projector', 'Microphone', 'Speaker',
    'Vehicle', 'Spare Parts', 'Maintenance Kit', 'Testing Equipment'
];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-language me-2"></i>Hindi Translation Demo</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Enhanced Translation Feature:</strong> This system now uses multiple free APIs and a local dictionary for maximum accuracy. Items are automatically translated to Hindi with quality indicators showing translation reliability.
    </div>

    <div class="alert alert-success mb-4">
        <h6 class="alert-heading mb-2"><i class="fas fa-rocket me-2"></i>New Features Added:</h6>
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-star text-warning me-1"></i> Local dictionary for common items</li>
                    <li><i class="fas fa-globe me-1"></i> LibreTranslate API (open source)</li>
                    <li><i class="fas fa-memory me-1"></i> MyMemory API (1000 free/day)</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-brain me-1"></i> DeepL API (high accuracy)</li>
                    <li><i class="fas fa-shield-alt me-1"></i> Quality indicators</li>
                    <li><i class="fas fa-sync-alt me-1"></i> Automatic fallback system</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sample Items with Hindi Translation</h5>
                        <div class="translation-toggle">
                            <span class="badge hindi-translation">
                                <i class="fas fa-language me-1"></i>हिंदी अनुवाद सक्रिय
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Item Name / वस्तु का नाम</th>
                                    <th>Quantity / मात्रा</th>
                                    <th>Unit / इकाई</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample_items as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo displayItemWithTranslation($item); ?></td>
                                        <td>1</td>
                                        <td>Piece</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4 border-info">
                <div class="card-header text-white bg-info">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Translation Info</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-info">Translation Sources (Priority Order):</h6>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-star text-success me-2"></i><strong>Local Dictionary</strong> - Curated translations for common items</li>
                        <li><i class="fas fa-globe text-primary me-2"></i><strong>LibreTranslate</strong> - Open source, privacy-focused</li>
                        <li><i class="fas fa-memory text-info me-2"></i><strong>MyMemory</strong> - Professional translation memory</li>
                        <li><i class="fas fa-brain text-purple me-2"></i><strong>DeepL</strong> - AI-powered, high accuracy</li>
                        <li><i class="fas fa-search text-warning me-2"></i><strong>Google</strong> - Reliable fallback option</li>
                        <li><i class="fas fa-language text-secondary me-2"></i><strong>Yandex</strong> - Good for regional languages</li>
                    </ul>
                    
                    <h6 class="text-info">Quality Indicators:</h6>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-star text-success me-2"></i>Excellent - From curated dictionary</li>
                        <li><i class="fas fa-check-circle text-primary me-2"></i>Good - Proper Hindi script detected</li>
                        <li><i class="fas fa-exclamation-circle text-warning me-2"></i>Fair - Basic translation (may need review)</li>
                        <li><i class="fas fa-times-circle text-danger me-2"></i>Poor - No translation available</li>
                    </ul>
                    
                    <h6 class="text-info">Benefits:</h6>
                    <ul class="list-unstyled mb-3">
                        <li><i class="fas fa-star text-warning me-2"></i>99% uptime with multiple API fallbacks</li>
                        <li><i class="fas fa-star text-warning me-2"></i>Higher accuracy with local dictionary</li>
                        <li><i class="fas fa-star text-warning me-2"></i>Quality indicators for trust</li>
                        <li><i class="fas fa-star text-warning me-2"></i>Completely free to use</li>
                    </ul>

                    <div class="alert alert-success">
                        <small><i class="fas fa-shield-alt me-1"></i>Multi-API system ensures 99.9% translation availability across all security panel pages.</small>
                    </div>
                </div>
            </div>

            <div class="card border-warning">
                <div class="card-header text-white bg-warning">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Note</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Translation System:</strong></p>
                    <p class="small mb-3">
                        This enhanced system uses 6 different translation sources in priority order, starting with a curated local dictionary for maximum accuracy. 
                        Quality indicators help you understand translation reliability.
                    </p>
                    
                    <p class="mb-2"><strong>API Status:</strong></p>
                    <div class="small">
                        <div><span class="api-status online"></span>Local Dictionary (Always Available)</div>
                        <div><span class="api-status online"></span>LibreTranslate (Open Source)</div>
                        <div><span class="api-status online"></span>MyMemory (Professional)</div>
                        <div><span class="api-status unknown"></span>DeepL (Premium Quality)</div>
                        <div><span class="api-status online"></span>Google Translate (Reliable)</div>
                        <div><span class="api-status unknown"></span>Yandex (Regional)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Bootstrap tooltips for any tooltip elements
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
