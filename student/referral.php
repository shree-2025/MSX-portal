<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
require_once '../includes/ReferralHelper.php';

// Ensure user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}
$studentId = $_SESSION['user_id'];

// Initialize ReferralHelper
$referralHelper = new ReferralHelper($conn);

// Handle reward redemption
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward'])) {
    $rewardId = intval($_POST['reward_id']);
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    
    $result = $referralHelper->redeemReward($studentId, $rewardId, $shippingAddress);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get referral data
$referralCode = $referralHelper->getReferralCode($studentId);
$referralUrl = $referralHelper->getReferralUrl($studentId);
$walletBalance = $referralHelper->getWalletBalance($studentId);
$referralStats = $referralHelper->getReferralStats($studentId);
$availableRewards = $referralHelper->getAvailableRewards();
$redemptionHistory = $referralHelper->getRedemptionHistory($studentId);

// Debug information
error_log("Referral Code: " . $referralCode);
error_log("Referral URL: " . $referralUrl);
error_log("SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set'));
error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set'));
error_log("SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set'));
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set'));

// Get recent referrals
$recentReferrals = [];
$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.created_at, u.status
    FROM users u
    WHERE u.referred_by = ?
    ORDER BY u.created_at DESC
    LIMIT 5");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentReferrals[] = $row;
}

// Set page title
$pageTitle = 'MSX Coins & Rewards';

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="h2 mb-0">
            <i class="fas fa-coins text-warning me-2"></i>MSX Coins & Rewards
        </h1>
        <div class="badge bg-primary bg-opacity-10 text-primary p-2 px-3 rounded-pill">
            <i class="fas fa-wallet me-2"></i>
            <span class="fw-bold"><?php echo number_format($walletBalance); ?> MSX Coins</span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show mb-4" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php
    // Generate the referral message with clickable link
    // Note: WhatsApp will automatically detect and make URLs clickable if they are on their own line
    $referralMessage = "🌟 *Your Future in IT Starts Here!* 🌟

" .
    "Hey! I'm currently learning at *MSX Academy* 💻 and it's been a game-changer for me.

" .
    "They offer industry-ready courses with hands-on projects, expert trainers, and a fun learning environment. 🚀

" .
    "🔥 *Why you should join?*
" .
    "✅ Learn the latest IT skills (Coding, Cloud, AI & more)
" .
    "✅ Earn *MSX Coins 🪙* when you enroll or refer friends
" .
    "✅ Redeem exciting goodies 🎁 like *Smart Watches ⌚, Backpacks 🎒, Bottles 🥤 & more*
" .
    "✅ Build your career with practical knowledge & internships

" .
    "👉 Click the link below to explore and enroll:

" .
    $referralUrl . "

" .
    "💡 Bonus: When you join with my link, *we both earn MSX Coins* — double rewards! 🪙💰

" .
    "Let's grow together with *MSX Academy* ✨
" .
    "#HappyLearning #HappyCoding";
    
    // Format message for WhatsApp with proper line breaks
    $formattedMessage = str_replace("\n", "%0A", $referralMessage);
    // Create WhatsApp sharing URL that will open in the app with the message pre-filled
    // Using the WhatsApp intent URL scheme for better mobile compatibility
    $whatsappUrl = 'whatsapp://send?text=' . urlencode($referralMessage);
    // Fallback for web
    $whatsappWebUrl = 'https://web.whatsapp.com/send?text=' . urlencode($referralMessage);
    ?>

    <!-- Referral Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-user-plus text-primary me-2"></i>Invite Friends & Earn MSX Coins
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="p-4 bg-light rounded-3 h-100">
                        <h6 class="mb-3">Your Referral Link</h6>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="referralLink" value="<?php echo htmlspecialchars($referralUrl); ?>" readonly>
                            <button class="btn btn-primary" type="button" id="copyReferralLink" data-bs-toggle="tooltip" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo $whatsappUrl; ?>" 
                               onclick="window.open('<?php echo $whatsappWebUrl; ?>', '_blank'); return false;" 
                               class="btn btn-success" 
                               target="_blank">
                                <i class="fab fa-whatsapp me-1"></i> Share on WhatsApp
                            </a>
                            <a href="mailto:?subject=Join%20me%20on%20MSX%20Portal&body=<?php echo urlencode("I thought you might be interested in checking out MSX Portal. Use my referral link to get started: ") . urlencode($referralUrl); ?>" 
                               class="btn btn-primary" 
                               target="_blank">
                                <i class="fas fa-envelope me-1"></i> Share via Email
                            </a>
                            <button class="btn btn-outline-secondary" id="copyMessageBtn" data-bs-toggle="tooltip" title="Copy message to clipboard">
                                <i class="far fa-copy me-1"></i> Copy Message
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-4 bg-light rounded-3 h-100">
                        <h6 class="mb-4">Your Referral Stats</h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 bg-white rounded-3 text-center">
                                    <div class="h2 fw-bold text-primary mb-1"><?php echo $referralStats['total_referrals']; ?></div>
                                    <div class="small text-muted">Total Referrals</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-white rounded-3 text-center">
                                    <div class="h2 fw-bold text-success mb-1"><?php echo $referralStats['active_referrals']; ?></div>
                                    <div class="small text-muted">Active Students</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-white rounded-3 text-center">
                                    <div class="h2 fw-bold text-warning mb-1"><?php echo number_format($referralStats['total_earned']); ?></div>
                                    <div class="small text-muted">Total MSX Coins Earned</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($recentReferrals)): ?>
                <div class="mt-4">
                    <h6 class="mb-3">Recent Referrals</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Date Referred</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReferrals as $referral): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($referral['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($referral['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($referral['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $referral['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($referral['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="referrals.php" class="btn btn-sm btn-outline-primary">View All Referrals</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rewards Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-gift text-danger me-2"></i>Redeem Your MSX Coins
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($availableRewards)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-gift fa-3x text-muted opacity-25"></i>
                    </div>
                    <h5>No rewards available at the moment</h5>
                    <p class="text-muted">Check back later for exciting rewards you can redeem with your MSX Coins.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($availableRewards as $reward): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <?php if (!empty($reward['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($reward['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($reward['name']); ?>">
                                <?php else: ?>
                                    <div class="bg-light text-center py-5">
                                        <i class="fas fa-gift fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($reward['name']); ?></h5>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($reward['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <span class="badge bg-warning bg-opacity-10 text-warning">
                                                <i class="fas fa-coins me-1"></i> <?php echo number_format($reward['coin_cost']); ?> MSX Coins
                                            </span>
                                            <?php if ($reward['stock'] !== null): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info ms-2">
                                                    <?php echo $reward['stock']; ?> left
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#redeemModal<?php echo $reward['id']; ?>">
                                            Redeem Now
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Redeem Modal -->
                            <div class="modal fade" id="redeemModal<?php echo $reward['id']; ?>" tabindex="-1" aria-labelledby="redeemModalLabel<?php echo $reward['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="redeemModalLabel<?php echo $reward['id']; ?>">
                                                <i class="fas fa-gift text-danger me-2"></i>Redeem <?php echo htmlspecialchars($reward['name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post" action="">
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    This reward costs <strong><?php echo number_format($reward['coin_cost']); ?> MSX Coins</strong>.
                                                    Your current balance: <strong><?php echo number_format($walletBalance); ?> MSX Coins</strong>.
                                                </div>
                                                
                                                <?php if ($walletBalance < $reward['coin_cost']): ?>
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        You don't have enough MSX Coins to redeem this reward. 
                                                        <a href="#referral-section" class="alert-link">Earn more coins</a> by referring friends.
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-3">
                                                        <label for="shippingAddress<?php echo $reward['id']; ?>" class="form-label">Shipping Address</label>
                                                        <textarea class="form-control" id="shippingAddress<?php echo $reward['id']; ?>" 
                                                                  name="shipping_address" rows="3" required
                                                                  placeholder="Enter your complete shipping address"></textarea>
                                                        <div class="form-text">We'll ship your reward to this address.</div>
                                                    </div>
                                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <?php if ($walletBalance >= $reward['coin_cost']): ?>
                                                    <button type="submit" name="redeem_reward" class="btn btn-primary">
                                                        <i class="fas fa-check-circle me-1"></i> Confirm Redemption
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <i class="fas fa-history text-primary me-2"></i>Redemption History
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($redemptionHistory)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-inbox fa-3x text-muted opacity-25"></i>
                    </div>
                    <h5>No redemption history</h5>
                    <p class="text-muted">Your redeemed rewards will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reward</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redemptionHistory as $redemption): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($redemption['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($redemption['reward_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning bg-opacity-10 text-warning">
                                            <i class="fas fa-coins me-1"></i> <?php echo number_format($redemption['coin_cost']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = [
                                            'pending' => 'bg-warning',
                                            'processing' => 'bg-info',
                                            'shipped' => 'bg-primary',
                                            'delivered' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ][$redemption['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($redemption['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal<?php echo $redemption['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>

                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $redemption['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-info-circle me-2"></i>Redemption Details
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <h6>Reward</h6>
                                                            <p class="mb-1"><?php echo htmlspecialchars($redemption['reward_name']); ?></p>
                                                            <p class="text-muted small mb-0"><?php echo number_format($redemption['coin_cost']); ?> MSX Coins</p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <h6>Status</h6>
                                                            <span class="badge <?php echo $statusClass; ?>">
                                                                <?php echo ucfirst($redemption['status']); ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <?php if (!empty($redemption['shipping_address'])): ?>
                                                            <div class="mb-3">
                                                                <h6>Shipping Address</h6>
                                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($redemption['shipping_address'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($redemption['notes'])): ?>
                                                            <div class="mb-3">
                                                                <h6>Notes</h6>
                                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($redemption['notes'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="mt-4">
                                                            <p class="text-muted small mb-0">
                                                                <i class="far fa-clock me-1"></i> 
                                                                Redeemed on <?php echo date('F j, Y \a\t g:i A', strtotime($redemption['created_at'])); ?>
                                                            </p>
                                                            <?php if ($redemption['updated_at'] !== $redemption['created_at']): ?>
                                                                <p class="text-muted small mb-0">
                                                                    <i class="fas fa-sync-alt me-1"></i> 
                                                                    Last updated on <?php echo date('F j, Y \a\t g:i A', strtotime($redemption['updated_at'])); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden textarea for copying message -->
<textarea id="messageToCopy" style="position: absolute; left: -9999px;"><?php echo htmlspecialchars($referralMessage); ?></textarea>

<script>
$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Copy referral link to clipboard
    $('#copyReferralLink').click(function() {
        var copyText = document.getElementById("referralLink");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        // Show tooltip
        var tooltip = bootstrap.Tooltip.getInstance(this);
        var originalTitle = $(this).attr('data-bs-original-title');
        tooltip.setContent({'.tooltip-inner': 'Copied!'});
        
        // Reset tooltip after 2 seconds
        setTimeout(function() {
            tooltip.setContent({'.tooltip-inner': originalTitle});
        }, 2000);
    });
    
    // Copy message to clipboard
    $('#copyMessageBtn').click(function() {
        var copyText = document.getElementById("messageToCopy");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        
        // Show tooltip
        var tooltip = bootstrap.Tooltip.getInstance(this);
        var originalTitle = $(this).attr('data-bs-original-title') || 'Copy message';
        tooltip.setContent({'.tooltip-inner': 'Message copied!'});
        
        // Reset tooltip after 2 seconds
        setTimeout(function() {
            tooltip.setContent({'.tooltip-inner': originalTitle});
        }, 2000);
    });
});
</script>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include 'includes/footer.php'; ?>
