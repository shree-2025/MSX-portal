<?php
class ReferralHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get student's wallet balance
     */
    public function getWalletBalance($studentId) {
        $stmt = $this->conn->prepare("SELECT balance FROM student_wallet WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['balance'];
        }
        
        return 0;
    }
    
    /**
     * Add coins to student's wallet
     */
    public function addCoins($studentId, $amount, $description, $referenceType = null, $referenceId = null) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // First ensure the wallet exists
            $walletId = $this->getWalletId($studentId);
            
            // Now update the balance
            $stmt = $this->conn->prepare("
                UPDATE student_wallet 
                SET balance = balance + ? 
                WHERE id = ?");
            $stmt->bind_param("ii", $amount, $walletId);
            $stmt->execute();
            
            // Record transaction
            $this->recordTransaction($walletId, $amount, 'credit', $description, $referenceType, $referenceId);
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error adding coins: " . $e->getMessage());
            
            // If it's a duplicate entry error, try one more time
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                error_log("Retrying addCoins after duplicate entry error");
                return $this->addCoins($studentId, $amount, $description, $referenceType, $referenceId);
            }
            
            return false;
        }
    }
    
    /**
     * Deduct coins from student's wallet
     */
    public function deductCoins($studentId, $amount, $description, $referenceType = null, $referenceId = null) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // First ensure the wallet exists
            $walletId = $this->getWalletId($studentId);
            
            // Check if student has sufficient balance
            $stmt = $this->conn->prepare("SELECT balance FROM student_wallet WHERE id = ?");
            $stmt->bind_param("i", $walletId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Wallet not found for student ID: " . $studentId);
            }
            
            $balance = $result->fetch_assoc()['balance'];
            if ($balance < $amount) {
                throw new Exception("Insufficient balance. Available: $balance, Required: $amount");
            }
            
            // Update wallet balance using the wallet ID for more reliable updates
            $stmt = $this->conn->prepare("
                UPDATE student_wallet 
                SET balance = balance - ? 
                WHERE id = ?");
            $stmt->bind_param("ii", $amount, $walletId);
            $stmt->execute();
            
            // Record transaction
            $this->recordTransaction($walletId, $amount, 'debit', $description, $referenceType, $referenceId);
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error deducting coins: " . $e->getMessage());
            
            // If it's a duplicate entry error, try one more time
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                error_log("Retrying deductCoins after duplicate entry error");
                return $this->deductCoins($studentId, $amount, $description, $referenceType, $referenceId);
            }
            
            return false;
        }
    }
    
    /**
     * Record a wallet transaction
     */
    private function recordTransaction($walletId, $amount, $type, $description, $referenceType, $referenceId) {
        $stmt = $this->conn->prepare("
            INSERT INTO wallet_transactions 
            (wallet_id, amount, type, description, reference_type, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssi", $walletId, $amount, $type, $description, $referenceType, $referenceId);
        $stmt->execute();
    }
    
    /**
     * Get wallet ID for a student
     */
    private function getWalletId($studentId) {
        // First try to get existing wallet
        $stmt = $this->conn->prepare("SELECT id FROM student_wallet WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        }
        
        // If no wallet exists, try to insert one with ON DUPLICATE KEY
        try {
            $stmt = $this->conn->prepare("INSERT INTO student_wallet (student_id, balance) VALUES (?, 0) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            
            // If we're here, the insert was successful
            return $this->conn->insert_id;
        } catch (Exception $e) {
            // If there was a race condition and another process created the wallet, get the existing one
            $stmt = $this->conn->prepare("SELECT id FROM student_wallet WHERE student_id = ?");
            $stmt->bind_param("i", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc()['id'];
            }
            
            // If we still don't have a wallet, rethrow the exception
            throw $e;
        }
    }
    
    /**
     * Generate an attractive referral message with WhatsApp sharing
     * 
     * @param string $referralCode The user's referral code
     * @return array Contains 'message' and 'whatsapp_url' for sharing
     */
    public function getReferralMessage($referralCode) {
        $referralLink = rtrim(BASE_URL, '/') . '/register.php?ref=' . urlencode($referralCode);
        
        $message = "🌟 *Your Future in IT Starts Here!* 🌟

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
        "👉 Click here to explore and enroll using my link:  
" .
        "$referralLink  

" .
        "💡 Bonus: When you join with my link, *we both earn MSX Coins* — double rewards! 🪙💰

" .
        "Let's grow together with *MSX Academy* ✨
" .
        "#HappyLearning #HappyCoding";
        
        return [
            'message' => $message,
            'whatsapp_url' => 'https://api.whatsapp.com/send?text=' . urlencode($message),
            'referral_link' => $referralLink
        ];
    }
    
    /**
     * Get student's referral code, generate one if it doesn't exist
     */
    public function getReferralCode($studentId) {
        // First try to get existing referral code
        $stmt = $this->conn->prepare("SELECT id, referral_code FROM users WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // If user exists but has no referral code, generate one
            if (empty($user['referral_code'])) {
                $newCode = $this->generateUniqueReferralCode();
                $updateStmt = $this->conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newCode, $studentId);
                $updateStmt->execute();
                return $newCode;
            }
            return $user['referral_code'];
        }
        
        return null;
    }
    
    /**
     * Generate a unique referral code
     */
    private function generateUniqueReferralCode() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        $codeLength = 8;
        
        do {
            $code = '';
            for ($i = 0; $i < $codeLength; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            // Check if code already exists
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE referral_code = ?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] == 0) {
                return $code;
            }
        } while (true);
    }
    
    /**
     * Get student's referral URL
     */
    public function getReferralUrl($studentId) {
        $code = $this->getReferralCode($studentId);
        if (!$code) {
            error_log("No referral code found for student ID: " . $studentId);
            return null;
        }
        
        // Use the BASE_URL constant from config
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/config.php';
        }
        
        // Ensure BASE_URL doesn't have a trailing slash
        $baseUrl = rtrim(BASE_URL, '/');
        
        // Build the final URL
        $url = $baseUrl . '/register.php?ref=' . urlencode($code);
        
        // Log for debugging
        error_log("Referral URL - " . 
                 "BASE_URL: " . BASE_URL . ", " .
                 "Code: $code, " .
                 "Final URL: $url");
        
        return $url;
    }
    
    /**
     * Get student's referral stats
     */
    public function getReferralStats($studentId) {
        $stats = [
            'total_referrals' => 0,
            'active_referrals' => 0,
            'total_earned' => 0
        ];
        
        // Get total referrals
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active
            FROM users u 
            WHERE u.referred_by = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['total_referrals'] = $row['total'];
            $stats['active_referrals'] = $row['active'];
        }
        
        // Get total earned from referrals
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_earned
            FROM wallet_transactions wt
            JOIN student_wallet sw ON wt.wallet_id = sw.id
            WHERE sw.student_id = ? AND wt.type = 'credit' AND wt.reference_type = 'referral'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stats['total_earned'] = $row['total_earned'];
        }
        
        return $stats;
    }
    
    /**
     * Get list of available rewards
     */
    public function getAvailableRewards() {
        $rewards = [];
        $result = $this->conn->query("
            SELECT * FROM rewards 
            WHERE is_active = 1 AND (stock > 0 OR stock IS NULL)
            ORDER BY coin_cost ASC");
        
        while ($row = $result->fetch_assoc()) {
            $rewards[] = $row;
        }
        
        return $rewards;
    }
    
    /**
     * Redeem a reward
     */
    public function redeemReward($studentId, $rewardId, $shippingAddress = '') {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Get reward details
            $stmt = $this->conn->prepare("
                SELECT * FROM rewards 
                WHERE id = ? AND is_active = 1 AND (stock > 0 OR stock IS NULL)
                FOR UPDATE");
            $stmt->bind_param("i", $rewardId);
            $stmt->execute();
            $reward = $stmt->get_result()->fetch_assoc();
            
            if (!$reward) {
                throw new Exception("Reward not available");
            }
            
            // Check if user has enough coins
            $currentBalance = $this->getWalletBalance($studentId);
            if ($currentBalance < $reward['coin_cost']) {
                throw new Exception("Insufficient MSX Coins");
            }
            
            // Deduct coins
            $this->deductCoins(
                $studentId, 
                $reward['coin_cost'], 
                "Redeemed reward: " . $reward['name'],
                'reward_redemption',
                $rewardId
            );
            
            // Create redemption record
            $stmt = $this->conn->prepare("
                INSERT INTO reward_redemptions 
                (student_id, reward_id, status, shipping_address) 
                VALUES (?, ?, 'pending', ?)");
            $status = 'pending';
            $stmt->bind_param("iis", $studentId, $rewardId, $shippingAddress);
            $stmt->execute();
            
            // Update reward stock if applicable
            if ($reward['stock'] !== null) {
                $stmt = $this->conn->prepare("
                    UPDATE rewards 
                    SET stock = stock - 1 
                    WHERE id = ? AND (stock > 0 OR stock IS NULL)");
                $stmt->bind_param("i", $rewardId);
                $stmt->execute();
                
                if ($stmt->affected_rows === 0) {
                    throw new Exception("Reward is out of stock");
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Reward redeemed successfully!',
                'redemption_id' => $this->conn->insert_id
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get student's redemption history
     */
    public function getRedemptionHistory($studentId) {
        $redemptions = [];
        $stmt = $this->conn->prepare("
            SELECT rr.*, r.name as reward_name, r.coin_cost, r.image
            FROM reward_redemptions rr
            JOIN rewards r ON rr.reward_id = r.id
            WHERE rr.student_id = ?
            ORDER BY rr.created_at DESC");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $redemptions[] = $row;
        }
        
        return $redemptions;
    }
    
    /**
     * Process a new referral (to be called when a referred user signs up)
     */
    public function processReferral($referredUserId, $referralCode) {
        // Get referrer's ID
        $stmt = $this->conn->prepare("
            SELECT id FROM users 
            WHERE referral_code = ? AND id != ?");
        $stmt->bind_param("si", $referralCode, $referredUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false; // Invalid referral code
        }
        
        $referrerId = $result->fetch_assoc()['id'];
        
        // Update referred user's record
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET referred_by = ? 
            WHERE id = ?");
        $stmt->bind_param("ii", $referrerId, $referredUserId);
        $stmt->execute();
        
        // Award bonus for signup (e.g., 20 coins)
        $this->addCoins(
            $referrerId, 
            20, 
            "Referral bonus: New user signup",
            'referral',
            $referredUserId
        );
        
        return true;
    }
    
    /**
     * Award coins for course enrollment (to be called when a referred user enrolls in a course)
     */
    public function awardCourseEnrollmentBonus($studentId) {
        // Check if user was referred
        $stmt = $this->conn->prepare("
            SELECT referred_by 
            FROM users 
            WHERE id = ? AND referred_by IS NOT NULL");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false; // Not a referred user
        }
        
        $referrerId = $result->fetch_assoc()['referred_by'];
        
        // Check if already awarded for this course
        $stmt = $this->conn->prepare("
            SELECT 1 
            FROM wallet_transactions wt
            JOIN student_wallet sw ON wt.wallet_id = sw.id
            WHERE sw.student_id = ? 
            AND wt.reference_type = 'enrollment' 
            AND wt.reference_id = ?");
        $stmt->bind_param("ii", $referrerId, $studentId);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            return false; // Already awarded for this enrollment
        }
        
        // Award coins for course enrollment (e.g., 100 coins)
        return $this->addCoins(
            $referrerId, 
            100, 
            "Referral bonus: Course enrollment",
            'enrollment',
            $studentId
        );
    }
}
