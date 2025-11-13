// Sign Up Page JavaScript
class SignUpForm {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadDepartmentsAndMajors();
        this.setupRealTimeValidation();
    }

    setupEventListeners() {
        const form = document.getElementById('signupForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const roleSelect = document.getElementById('role');

        // Password confirmation validation
        confirmPassword.addEventListener('input', () => {
            this.validatePasswordMatch();
        });

        // Role change handler
        roleSelect.addEventListener('change', () => {
            this.toggleRoleFields();
        });

        // Form submission
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });

        // Real-time validation for user ID
        document.getElementById('user_id').addEventListener('blur', () => {
            this.validateUserId();
        });

        // Real-time validation for email
        document.getElementById('email').addEventListener('blur', () => {
            this.validateEmail();
        });
    }

    setupRealTimeValidation() {
        // Add input event listeners for real-time validation
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
        });
    }

    validateField(input) {
        const value = input.value.trim();
        const fieldName = input.name;
        
        switch (fieldName) {
            case 'first_name':
            case 'last_name':
                return this.validateName(input, value);
            case 'email':
                return this.validateEmail(input, value);
            case 'user_id':
                return this.validateUserId(input, value);
            case 'dob':
                return this.validateDateOfBirth(input, value);
            default:
                return this.validateRequired(input, value);
        }
    }

    validateName(input, value) {
        if (!value) {
            this.showError(input, 'This field is required');
            return false;
        }
        
        if (value.length < 2) {
            this.showError(input, 'Name must be at least 2 characters long');
            return false;
        }
        
        if (!/^[a-zA-Z\s\-']+$/.test(value)) {
            this.showError(input, 'Name can only contain letters, spaces, hyphens, and apostrophes');
            return false;
        }
        
        this.clearError(input);
        return true;
    }

    validateEmail(input = null, value = null) {
        if (!input) {
            input = document.getElementById('email');
            value = input.value.trim();
        }
        
        if (!value) {
            this.showError(input, 'Email is required');
            return false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            this.showError(input, 'Please enter a valid email address');
            return false;
        }
        
        // Check if email domain is valid (basic check)
        const domain = value.split('@')[1];
        const validDomains = ['edu', 'ac', 'university', 'college', 'school'];
        const hasValidDomain = validDomains.some(valid => domain.includes(valid));
        
        if (!hasValidDomain) {
            this.showWarning(input, 'Please use your institutional email address if available');
        } else {
            this.clearError(input);
        }
        
        return true;
    }

    validateUserId(input = null, value = null) {
        if (!input) {
            input = document.getElementById('user_id');
            value = input.value.trim();
        }
        
        if (!value) {
            this.showError(input, 'User ID is required');
            return false;
        }
        
        if (!/^\d+$/.test(value)) {
            this.showError(input, 'User ID must contain only numbers');
            return false;
        }
        
        if (value.length < 5) {
            this.showError(input, 'User ID must be at least 5 digits long');
            return false;
        }
        
        // Simulate checking if user ID already exists
        this.checkUserIdAvailability(value).then(available => {
            if (!available) {
                this.showError(input, 'This User ID is already registered');
                return false;
            } else {
                this.clearError(input);
                return true;
            }
        });
        
        return true;
    }

    async checkUserIdAvailability(userId) {
        // Simulate API call to check user ID availability
        return new Promise((resolve) => {
            setTimeout(() => {
                // In real implementation, this would be an API call
                const takenIds = ['12345', '67890', '11111'];
                resolve(!takenIds.includes(userId));
            }, 500);
        });
    }

    validateDateOfBirth(input, value) {
        if (!value) {
            this.showError(input, 'Date of birth is required');
            return false;
        }
        
        const dob = new Date(value);
        const today = new Date();
        const minAge = 16; // Minimum age requirement
        const maxAge = 100; // Maximum age requirement
        
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        if (age < minAge) {
            this.showError(input, `You must be at least ${minAge} years old`);
            return false;
        }
        
        if (age > maxAge) {
            this.showError(input, `Please enter a valid date of birth`);
            return false;
        }
        
        this.clearError(input);
        return true;
    }

    validateRequired(input, value) {
        if (!value) {
            this.showError(input, 'This field is required');
            return false;
        }
        
        this.clearError(input);
        return true;
    }

    validatePasswordMatch() {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const message = document.getElementById('passwordMessage');
        
        if (password.value !== confirmPassword.value) {
            this.showError(confirmPassword, 'Passwords do not match!');
            message.style.display = 'block';
            return false;
        } else {
            this.clearError(confirmPassword);
            message.style.display = 'none';
            
            // Additional password strength validation
            return this.validatePasswordStrength(password.value);
        }
    }

    validatePasswordStrength(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        const metRequirements = Object.values(requirements).filter(Boolean).length;
        const strength = (metRequirements / Object.keys(requirements).length) * 100;
        
        this.updatePasswordStrengthIndicator(strength);
        
        if (strength < 60) {
            this.showError(document.getElementById('password'), 'Password is too weak. Include uppercase, lowercase, numbers, and special characters.');
            return false;
        }
        
        this.clearError(document.getElementById('password'));
        return true;
    }

    updatePasswordStrengthIndicator(strength) {
        let indicator = document.getElementById('passwordStrengthIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'passwordStrengthIndicator';
            indicator.className = 'password-strength';
            document.getElementById('password').parentNode.appendChild(indicator);
        }
        
        let strengthText = '';
        let strengthClass = '';
        
        if (strength < 40) {
            strengthText = 'Weak';
            strengthClass = 'weak';
        } else if (strength < 70) {
            strengthText = 'Medium';
            strengthClass = 'medium';
        } else {
            strengthText = 'Strong';
            strengthClass = 'strong';
        }
        
        indicator.innerHTML = `
            <div class="strength-bar">
                <div class="strength-fill ${strengthClass}" style="width: ${strength}%"></div>
            </div>
            <span class="strength-text ${strengthClass}">${strengthText}</span>
        `;
    }

    toggleRoleFields() {
        const role = document.getElementById('role').value;
        
        // Hide all role-specific fields
        document.querySelectorAll('.role-specific-fields').forEach(field => {
            field.style.display = 'none';
            // Clear required attributes when hiding
            field.querySelectorAll('input, select').forEach(input => {
                input.removeAttribute('required');
                input.value = ''; // Clear values when hiding
            });
        });
        
        // Show fields specific to selected role and set required attributes
        if (role === 'student') {
            const studentFields = document.getElementById('studentFields');
            studentFields.style.display = 'block';
            studentFields.querySelectorAll('input, select').forEach(input => {
                input.setAttribute('required', 'required');
            });
        } else if (role === 'faculty') {
            const facultyFields = document.getElementById('facultyFields');
            facultyFields.style.display = 'block';
            facultyFields.querySelectorAll('input, select').forEach(input => {
                input.setAttribute('required', 'required');
            });
        } else if (role === 'intern') {
            const internFields = document.getElementById('internFields');
            internFields.style.display = 'block';
            internFields.querySelectorAll('input, select').forEach(input => {
                input.setAttribute('required', 'required');
            });
        }
    }

    async loadDepartmentsAndMajors() {
        try {
            // Simulate API calls to load departments and majors
            const departments = await this.fetchDepartments();
            const majors = await this.fetchMajors();
            
            this.populateDepartmentDropdowns(departments);
            this.populateMajorDropdown(majors);
        } catch (error) {
            console.error('Error loading data:', error);
        }
    }

    async fetchDepartments() {
        // Simulate API call
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve([
                    { department_id: 1, department_name: 'Computer Science', building_location: 'Tech Building' },
                    { department_id: 2, department_name: 'Mathematics', building_location: 'Science Building' },
                    { department_id: 3, department_name: 'Physics', building_location: 'Science Building' },
                    { department_id: 4, department_name: 'Engineering', building_location: 'Engineering Building' }
                ]);
            }, 500);
        });
    }

    async fetchMajors() {
        // Simulate API call
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve([
                    { major_id: 1, major_name: 'Computer Science', duration: 4, department_id: 1 },
                    { major_id: 2, major_name: 'Software Engineering', duration: 4, department_id: 1 },
                    { major_id: 3, major_name: 'Data Science', duration: 4, department_id: 1 },
                    { major_id: 4, major_name: 'Applied Mathematics', duration: 4, department_id: 2 }
                ]);
            }, 500);
        });
    }

    populateDepartmentDropdowns(departments) {
        const departmentSelects = [
            'department_id',
            'assigned_department'
        ];
        
        departmentSelects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                select.innerHTML = '<option value="">Select department</option>';
                departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.department_id;
                    option.textContent = dept.department_name;
                    select.appendChild(option);
                });
            }
        });
    }

    populateMajorDropdown(majors) {
        const select = document.getElementById('major_id');
        if (select) {
            select.innerHTML = '<option value="">Select major</option>';
            majors.forEach(major => {
                const option = document.createElement('option');
                option.value = major.major_id;
                option.textContent = major.major_name;
                select.appendChild(option);
            });
        }
    }

    async handleSubmit() {
        // Validate all fields before submission
        if (!this.validateAllFields()) {
            this.showGeneralError('Please fix the errors before submitting.');
            return;
        }

        const formData = new FormData(document.getElementById('signupForm'));
        const userData = this.prepareUserData(formData);

        try {
            // Show loading state
            this.setLoadingState(true);

            // Simulate API call to create user
            const result = await this.createUser(userData);
            
            if (result.success) {
                this.showSuccess('Registration successful! Redirecting to login...');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                this.showGeneralError(result.message || 'Registration failed. Please try again.');
            }
        } catch (error) {
            this.showGeneralError('An error occurred during registration. Please try again.');
            console.error('Registration error:', error);
        } finally {
            this.setLoadingState(false);
        }
    }

    prepareUserData(formData) {
        const role = formData.get('role');
        const userData = {
            user_id: parseInt(formData.get('user_id')),
            first_name: formData.get('first_name').trim(),
            last_name: formData.get('last_name').trim(),
            email: formData.get('email').trim(),
            password: formData.get('password'),
            role: role,
            dob: formData.get('dob')
        };

        // Add role-specific data
        if (role === 'student') {
            userData.student_data = {
                major_id: parseInt(formData.get('major_id')),
                year_of_study: parseInt(formData.get('year_of_study'))
            };
        } else if (role === 'faculty') {
            userData.faculty_data = {
                department_id: parseInt(formData.get('department_id')),
                designation: formData.get('designation').trim()
            };
        } else if (role === 'intern') {
            userData.intern_data = {
                assigned_department: parseInt(formData.get('assigned_department')),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date')
            };
        }

        return userData;
    }

    validateAllFields() {
        let isValid = true;

        // Validate basic fields
        const basicFields = ['first_name', 'last_name', 'email', 'user_id', 'dob', 'password'];
        basicFields.forEach(fieldName => {
            const input = document.querySelector(`[name="${fieldName}"]`);
            if (input && !this.validateField(input)) {
                isValid = false;
            }
        });

        // Validate password match
        if (!this.validatePasswordMatch()) {
            isValid = false;
        }

        // Validate role-specific fields
        const role = document.getElementById('role').value;
        if (role === 'student') {
            const studentFields = ['major_id', 'year_of_study'];
            studentFields.forEach(fieldName => {
                const input = document.querySelector(`[name="${fieldName}"]`);
                if (input && !this.validateField(input)) {
                    isValid = false;
                }
            });
        } else if (role === 'faculty') {
            const facultyFields = ['department_id', 'designation'];
            facultyFields.forEach(fieldName => {
                const input = document.querySelector(`[name="${fieldName}"]`);
                if (input && !this.validateField(input)) {
                    isValid = false;
                }
            });
        } else if (role === 'intern') {
            const internFields = ['assigned_department', 'start_date', 'end_date'];
            internFields.forEach(fieldName => {
                const input = document.querySelector(`[name="${fieldName}"]`);
                if (input && !this.validateField(input)) {
                    isValid = false;
                }
            });
        }

        return isValid;
    }

    async createUser(userData) {
        // Simulate API call to create user
        return new Promise((resolve) => {
            setTimeout(() => {
                // In real implementation, this would be a fetch call to your backend
                console.log('Creating user with data:', userData);
                
                // Simulate successful creation
                resolve({
                    success: true,
                    message: 'User created successfully',
                    user_id: userData.user_id
                });
                
                // Simulate error (for testing)
                // resolve({
                //     success: false,
                //     message: 'User ID already exists'
                // });
            }, 1500);
        });
    }

    setLoadingState(loading) {
        const submitButton = document.querySelector('#signupForm button[type="submit"]');
        const originalText = submitButton.textContent;
        
        if (loading) {
            submitButton.disabled = true;
            submitButton.textContent = 'Creating Account...';
            submitButton.style.opacity = '0.7';
        } else {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            submitButton.style.opacity = '1';
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

    showWarning(input, message) {
        this.clearError(input);
        
        const warningDiv = document.createElement('div');
        warningDiv.className = 'warning-message';
        warningDiv.textContent = message;
        warningDiv.style.color = 'orange';
        warningDiv.style.fontSize = '0.8em';
        warningDiv.style.marginTop = '5px';
        
        input.style.borderColor = 'orange';
        input.parentNode.appendChild(warningDiv);
    }

    clearError(input) {
        const errorDiv = input.parentNode.querySelector('.error-message');
        const warningDiv = input.parentNode.querySelector('.warning-message');
        
        if (errorDiv) errorDiv.remove();
        if (warningDiv) warningDiv.remove();
        
        input.style.borderColor = '#ccc';
    }

    showGeneralError(message) {
        // Remove existing general error
        const existingError = document.getElementById('generalError');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.id = 'generalError';
        errorDiv.className = 'general-error';
        errorDiv.textContent = message;
        errorDiv.style.color = 'red';
        errorDiv.style.backgroundColor = '#ffe6e6';
        errorDiv.style.padding = '10px';
        errorDiv.style.borderRadius = '5px';
        errorDiv.style.marginBottom = '15px';
        errorDiv.style.textAlign = 'center';
        
        const form = document.getElementById('signupForm');
        form.insertBefore(errorDiv, form.firstChild);
        
        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        
        const form = document.getElementById('signupForm');
        form.insertBefore(successDiv, form.firstChild);
        
        // Scroll to success message
        successDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Initialize the sign up form when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SignUpForm();
});