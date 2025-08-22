<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
require_once '../includes/ReferralHelper.php';

// Check if user is logged in and has student role
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['user_id'];
$referralHelper = new ReferralHelper($conn);

// Get the user's referral code
$referralCode = $referralHelper->getReferralCode($studentId);

// If no referral code exists, generate one
if (!$referralCode) {
    $referralCode = $referralHelper->generateUniqueReferralCode();
    // Save the referral code to the user's account
    $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $stmt->bind_param("si", $referralCode, $studentId);
    $stmt->execute();
}

// Get the referral message and sharing URL
$referralData = $referralHelper->getReferralMessage($referralCode);

$pageTitle = "Share Referral";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-share-alt"></i> Share Your Referral Link</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Share your referral link with friends and earn MSX Coins when they sign up!
                    </div>
                    
                    <div class="form-group">
                        <label for="referralLink">Your Referral Link:</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="referralLink" value="<?php echo htmlspecialchars($referralData['referral_link']); ?>" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn" data-toggle="tooltip" title="Copy to clipboard">
                                    <i class="far fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Share via:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo $referralData['whatsapp_url']; ?>" class="btn btn-success" target="_blank">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="mailto:?body=<?php echo urlencode($referralData['message']); ?>" class="btn btn-primary">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                            <button class="btn btn-info" id="copyMessageBtn" data-toggle="tooltip" title="Copy message">
                                <i class="far fa-copy"></i> Copy Message
                            </button>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Preview Message</h5>
                        </div>
                        <div class="card-body bg-light">
                            <div class="whatsapp-preview">
                                <?php echo nl2br(htmlspecialchars($referralData['message'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden textarea for copying -->
<textarea id="messageToCopy" style="position: absolute; left: -9999px;"><?php echo htmlspecialchars($referralData['message']); ?></textarea>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Copy referral link to clipboard
    $('#copyLinkBtn').click(function() {
        var copyText = document.getElementById("referralLink");
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices
        document.execCommand("copy");
        
        // Change button text temporarily
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<i class="fas fa-check"></i> Copied!');
        
        setTimeout(function() {
            $btn.html(originalText);
        }, 2000);
    });
    
    // Copy message to clipboard
    $('#copyMessageBtn').click(function() {
        var copyText = document.getElementById("messageToCopy");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        // Show tooltip
        $(this).attr('data-original-title', 'Message copied!').tooltip('show');
        
        // Reset tooltip after 2 seconds
        var $btn = $(this);
        setTimeout(function() {
            $btn.attr('data-original-title', 'Copy message').tooltip('hide');
        }, 2000);
    });
});
</script>

<style>
.whatsapp-preview {
    white-space: pre-wrap;
    font-family: -apple-system, system-ui, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    line-height: 1.5;
    color: #111b21;
    background-color: #e5ddd5;
    padding: 15px;
    border-radius: 7.5px;
    max-width: 85%;
    margin: 0 auto;
    position: relative;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
</style>

<?php include 'includes/footer.php'; ?>
