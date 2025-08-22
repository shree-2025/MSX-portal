                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <p class="text-muted small">
                        &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
                        <?php if (!isLoggedIn()): ?>
                            <br>
                            <a href="<?= SITE_URL ?>/login.php" class="text-muted">Back to Login</a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.1.3 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery 3.6.0 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
    // Password strength indicator
    function checkPasswordStrength(password) {
        let strength = 0;
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');
        
        // Check length
        if (password.length >= 8) {
            strength += 1;
            document.getElementById('length-req').classList.add('valid');
        } else {
            document.getElementById('length-req').classList.remove('valid');
        }
        
        // Check for uppercase letters
        if (/[A-Z]/.test(password)) {
            strength += 1;
            document.getElementById('uppercase-req').classList.add('valid');
        } else {
            document.getElementById('uppercase-req').classList.remove('valid');
        }
        
        // Check for numbers
        if (/[0-9]/.test(password)) {
            strength += 1;
            document.getElementById('number-req').classList.add('valid');
        } else {
            document.getElementById('number-req').classList.remove('valid');
        }
        
        // Check for special characters
        if (/[^A-Za-z0-9]/.test(password)) {
            strength += 1;
            document.getElementById('special-req').classList.add('valid');
        } else {
            document.getElementById('special-req').classList.remove('valid');
        }
        
        // Update strength bar
        const strengthPercent = (strength / 4) * 100;
        strengthBar.style.width = strengthPercent + '%';
        
        // Update strength text and color
        if (password.length === 0) {
            strengthBar.style.backgroundColor = '#e9ecef';
            strengthText.textContent = '';
            strengthText.className = 'text-muted small';
        } else if (strength <= 1) {
            strengthBar.style.backgroundColor = '#e74a3b';
            strengthText.textContent = 'Weak';
            strengthText.className = 'text-danger small';
        } else if (strength <= 2) {
            strengthBar.style.backgroundColor = '#f6c23e';
            strengthText.textContent = 'Moderate';
            strengthText.className = 'text-warning small';
        } else if (strength <= 3) {
            strengthBar.style.backgroundColor = '#36b9cc';
            strengthText.textContent = 'Good';
            strengthText.className = 'text-info small';
        } else {
            strengthBar.style.backgroundColor = '#1cc88a';
            strengthText.textContent = 'Strong';
            strengthText.className = 'text-success small';
        }
    }
    
    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.querySelector(`[onclick="togglePassword('${inputId}')"] i`);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Password strength check on input
        const passwordInput = document.getElementById('new_password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
        }
        
        // Password match validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        }
    });
    
    // Validate password match
    function validatePasswordMatch() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const matchText = document.getElementById('password-match-text');
        
        if (!newPassword || !confirmPassword) return;
        
        if (confirmPassword.value === '') {
            matchText.textContent = '';
            confirmPassword.classList.remove('is-invalid', 'is-valid');
        } else if (newPassword.value !== confirmPassword.value) {
            matchText.textContent = 'Passwords do not match';
            matchText.className = 'text-danger small';
            confirmPassword.classList.add('is-invalid');
            confirmPassword.classList.remove('is-valid');
            return false;
        } else {
            matchText.textContent = 'Passwords match';
            matchText.className = 'text-success small';
            confirmPassword.classList.remove('is-invalid');
            confirmPassword.classList.add('is-valid');
            return true;
        }
    }
    
    // Form validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Check if passwords match
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                event.preventDefault();
                confirmPassword.focus();
                return false;
            }
            
            // Check password strength (at least 8 characters, 1 uppercase, 1 number, 1 special char)
            if (newPassword) {
                const hasUpperCase = /[A-Z]/.test(newPassword.value);
                const hasNumber = /[0-9]/.test(newPassword.value);
                const hasSpecialChar = /[^A-Za-z0-9]/.test(newPassword.value);
                
                if (newPassword.value.length < 8 || !hasUpperCase || !hasNumber || !hasSpecialChar) {
                    event.preventDefault();
                    newPassword.focus();
                    return false;
                }
            }
            
            return true;
        });
    }
    </script>
</body>
</html>
