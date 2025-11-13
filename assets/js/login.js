// Login Page JavaScript
class LoginForm {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkRememberedUser();
        this.setupRealTimeValidation();
    }

    setupEventListeners() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        // Remember me functionality
        const rememberCheckbox = document.getElementById('rememberMe');
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', (e) => {
                this.toggleRememberMe(e.target.checked);
            });
        }

        // Enter key submission
        document.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleLogin();
            }
        });

        // Forgot password link
        const forgotPasswordLink = document.querySelector('a[href="#forgot-password"]');
        if (forgotPasswordLink) {
            forgotPasswordLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.showForgotPasswordModal();
            });
        }
    }

    setupRealTimeValidation() {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        emailInput.addEventListener('blur', () => {
            this.validateEmail();
        });

        passwordInput.addEventListener('blur', () => {
            this.validatePassword();
        });
    }

    validateEmail() {
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();

        if (!email) {
            this.showError(emailInput, 'Email is required');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showError(emailInput, 'Please enter a valid email address');
            return false;
        }

        this.clearError(emailInput);
        return true;
    }

    validatePassword() {
        const passwordInput = document.getElementById('password');
        const password = passwordInput.value;

        if (!password) {
            this.showError(passwordInput, 'Password is required');
            return false;
        }

        if (password.length < 6) {
            this.showError(passwordInput, 'Password must be at least 6 characters long');
            return false;
        }

        this.clearError(passwordInput);
        return true;
    }

    async handleLogin() {
        // Validate fields before submission
        if (!this.validateEmail() || !this.validatePassword()) {
            this.showGeneralError('Please fix the errors before logging in.');
            return;
        }

        const formData = {
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            rememberMe: document.getElementById('rememberMe')?.checked || false
        };

        try {
            // Show loading state
            this.setLoadingState(true);

            // Simulate API call for authentication
            const result = await this.authenticateUser(formData);
            
            if (result.success) {
                this.handleSuccessfulLogin(result.user, formData.rememberMe);
            } else {
                this.handleFailedLogin(result.message);
            }
        } catch (error) {
            this.handleFailedLogin('An error occurred during login. Please try again.');
            console.error('Login error:', error);
        } finally {
            this.setLoadingState(false);
        }
    }

    async authenticateUser(credentials) {
        // Simulate API call to authenticate user
        return new Promise((resolve) => {
            setTimeout(() => {
                // In real implementation, this would be a fetch call to your backend
                console.log('Authenticating user:', credentials.email);
                
                // Mock user database
                const mockUsers = [
                    {
                        user_id: 12345,
                        email: 'student@university.edu',
                        password: 'password123', // In real app, this would be hashed
                        role: 'student',
                        first_name: 'John',
                        last_name: 'Doe'
                    },
                    {
                        user_id: 67890,
                        email: 'faculty@university.edu',
                        password: 'password123',
                        role: 'faculty',
                        first_name: 'Sarah',
                        last_name: 'Johnson'
                    },
                    {
                        user_id: 11111,
                        email: 'intern@university.edu',
                        password: 'password123',
                        role: 'intern',
                        first_name: 'Alex',
                        last_name: 'Thompson'
                    }
                ];

                const user = mockUsers.find(u => 
                    u.email === credentials.email && 
                    u.password === credentials.password
                );

                if (user) {
                    resolve({
                        success: true,
                        user: user,
                        message: 'Login successful'
                    });
                } else {
                    resolve({
                        success: false,
                        message: 'Invalid email or password'
                    });
                }
            }, 1000);
        });
    }

    handleSuccessfulLogin(user, rememberMe) {
        // Store user data in session storage
        sessionStorage.setItem('currentUser', JSON.stringify(user));
        
        // If remember me is checked, store in local storage
        if (rememberMe) {
            localStorage.setItem('rememberedUser', JSON.stringify({
                email: user.email,
                rememberMe: true
            }));
        } else {
            localStorage.removeItem('rememberedUser');
        }

        // Store authentication token (simulated)
        const authToken = this.generateAuthToken(user);
        sessionStorage.setItem('authToken', authToken);

        // Show success message
        this.showSuccess('Login successful! Redirecting...');

        // Redirect based on user role
        setTimeout(() => {
            this.redirectToDashboard(user.role);
        }, 1500);
    }

    generateAuthToken(user) {
        // In real implementation, this would come from the server
        return btoa(JSON.stringify({
            user_id: user.user_id,
            email: user.email,
            role: user.role,
            timestamp: Date.now()
        }));
    }

    redirectToDashboard(role) {
        const dashboardRoutes = {
            student: 'student-dashboard.html',
            faculty: 'faculty-dashboard.html',
            intern: 'intern-dashboard.html',
            admin: 'admin-dashboard.html'
        };

        const route = dashboardRoutes[role] || 'dashboard.html';
        window.location.href = route;
    }

    handleFailedLogin(message) {
        this.showGeneralError(message);
        
        // Clear password field for security
        document.getElementById('password').value = '';
        document.getElementById('password').focus();
        
        // Add shake animation to form
        const form = document.querySelector('form');
        form.classList.add('shake');
        setTimeout(() => {
            form.classList.remove('shake');
        }, 500);
    }

    checkRememberedUser() {
        const remembered = localStorage.getItem('rememberedUser');
        if (remembered) {
            try {
                const userData = JSON.parse(remembered);
                if (userData.email && userData.rememberMe) {
                    document.getElementById('email').value = userData.email;
                    document.getElementById('rememberMe').checked = true;
                    document.getElementById('password').focus();
                }
            } catch (error) {
                console.error('Error parsing remembered user:', error);
                localStorage.removeItem('rememberedUser');
            }
        }
    }

    toggleRememberMe(remember) {
        if (!remember) {
            localStorage.removeItem('rememberedUser');
        }
    }

    showForgotPasswordModal() {
        const modalHtml = `
            <div class="modal-overlay" id="forgotPasswordModal">
                <div class="modal-content">
                    <h3>Reset Password</h3>
                    <p>Enter your email address and we'll send you instructions to reset your password.</p>
                    <form id="forgotPasswordForm">
                        <div class="form-group">
                            <label for="resetEmail">Email Address:</label>
                            <input type="email" id="resetEmail" name="email" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="action-btn">Send Reset Link</button>
                            <button type="button" class="action-btn secondary" id="cancelReset">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Add event listeners
        document.getElementById('forgotPasswordForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handlePasswordReset();
        });

        document.getElementById('cancelReset').addEventListener('click', () => {
            this.closeModal();
        });

        // Close modal when clicking outside
        document.getElementById('forgotPasswordModal').addEventListener('click', (e) => {
            if (e.target.id === 'forgotPasswordModal') {
                this.closeModal();
            }
        });
    }

    async handlePasswordReset() {
        const email = document.getElementById('resetEmail').value.trim();
        
        if (!this.validateEmailForReset(email)) {
            return;
        }

        try {
            this.setResetLoadingState(true);
            
            // Simulate API call for password reset
            const result = await this.sendPasswordResetEmail(email);
            
            if (result.success) {
                this.showResetSuccess('Password reset instructions have been sent to your email.');
                setTimeout(() => {
                    this.closeModal();
                }, 3000);
            } else {
                this.showResetError(result.message);
            }
        } catch (error) {
            this.showResetError('An error occurred. Please try again.');
        } finally {
            this.setResetLoadingState(false);
        }
    }

    validateEmailForReset(email) {
        const emailInput = document.getElementById('resetEmail');
        
        if (!email) {
            this.showError(emailInput, 'Email is required');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showError(emailInput, 'Please enter a valid email address');
            return false;
        }

        this.clearError(emailInput);
        return true;
    }

    async sendPasswordResetEmail(email) {
        // Simulate API call
        return new Promise((resolve) => {
            setTimeout(() => {
                console.log('Sending password reset email to:', email);
                resolve({
                    success: true,
                    message: 'Reset email sent successfully'
                });
            }, 1500);
        });
    }

    closeModal() {
        const modal = document.getElementById('forgotPasswordModal');
        if (modal) {
            modal.remove();
        }
    }

    setLoadingState(loading) {
        const submitButton = document.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        if (loading) {
            submitButton.disabled = true;
            submitButton.textContent = 'Signing In...';
            submitButton.style.opacity = '0.7';
        } else {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            submitButton.style.opacity = '1';
        }
    }

    setResetLoadingState(loading) {
        const submitButton = document.querySelector('#forgotPasswordForm button[type="submit"]');
        if (submitButton) {
            const originalText = submitButton.textContent;
            
            if (loading) {
                submitButton.disabled = true;
                submitButton.textContent = 'Sending...';
                submitButton.style.opacity = '0.7';
            } else {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                submitButton.style.opacity = '1';
            }
        }
    }

    showError(input, message) {
        this.clearError(input);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = 'red';
        errorDiv.style.fontSize = '0.8em';
        errorDiv.style.marginTop = '5px';
        
        input.style.borderColor = 'red';
        input.parentNode.appendChild(errorDiv);
    }

    clearError(input) {
        const errorDiv = input.parentNode.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
        input.style.borderColor = '#ccc';
    }

    showGeneralError(message) {
        // Remove existing general error
        const existingError = document.querySelector('.general-error');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'general-error';
        errorDiv.textContent = message;
        errorDiv.style.color = 'red';
        errorDiv.style.backgroundColor = '#ffe6e6';
        errorDiv.style.padding = '10px';
        errorDiv.style.borderRadius = '5px';
        errorDiv.style.marginBottom = '15px';
        errorDiv.style.textAlign = 'center';
        
        const form = document.querySelector('form');
        form.insertBefore(errorDiv, form.firstChild);
    }

    showSuccess(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.textContent = message;
        successDiv.style.color = 'green';
        successDiv.style.backgroundColor = '#e6ffe6';
        successDiv.style.padding = '10px';
        successDiv.style.borderRadius = '5px';
        successDiv.style.marginBottom = '15px';
        successDiv.style.textAlign = 'center';
        
        const form = document.querySelector('form');
        form.insertBefore(successDiv, form.firstChild);
    }

    showResetSuccess(message) {
        const form = document.getElementById('forgotPasswordForm');
        const existingMessage = form.querySelector('.reset-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const successDiv = document.createElement('div');
        successDiv.className = 'reset-message success';
        successDiv.textContent = message;
        successDiv.style.color = 'green';
        successDiv.style.marginBottom = '15px';
        successDiv.style.textAlign = 'center';
        
        form.insertBefore(successDiv, form.firstChild);
    }

    showResetError(message) {
        const form = document.getElementById('forgotPasswordForm');
        const existingMessage = form.querySelector('.reset-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'reset-message error';
        errorDiv.textContent = message;
        errorDiv.style.color = 'red';
        errorDiv.style.marginBottom = '15px';
        errorDiv.style.textAlign = 'center';
        
        form.insertBefore(errorDiv, form.firstChild);
    }
}

// Initialize the login form when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new LoginForm();
});