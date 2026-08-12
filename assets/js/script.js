/**
 * Graduate Job Connect - JavaScript Functions
 * Ethiopian & International University Project
 * Version: 2.0
 * 
 * Client-side validation, interactions, and UI enhancements
 */

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
    MAX_FILE_SIZE: 5 * 1024 * 1024, // 5MB
    ALLOWED_FILE_TYPES: ['application/pdf'],
    PASSWORD_MIN_LENGTH: 8,
    MAX_DESCRIPTION_LENGTH: 2000,
    MAX_SKILLS_LENGTH: 500,
    AUTO_HIDE_ALERT_DELAY: 5000,
    DEBOUNCE_DELAY: 300,
};

// ============================================
// VALIDATION FUNCTIONS
// ============================================

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean}
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate Ethiopian university email
 * @param {string} email - Email address to check
 * @returns {boolean}
 */
function isEthiopianUniversityEmail(email) {
    const ethiopianUniversities = [
        'aau.edu.et', 'mu.edu.et', 'bdu.edu.et', 'haramaya.edu.et',
        'ju.edu.et', 'su.edu.et', 'gmu.edu.et', 'dsu.edu.et',
        'uog.edu.et', 'jju.edu.et', 'wsu.edu.et', 'amum.edu.et',
        'adu.edu.et', 'mtu.edu.et', 'debreberhan.edu.et',
        'walia.edu.et', 'ac.edu.et', 'ecsu.edu.et', 'stmarys.edu.et'
    ];
    
    const domain = email.split('@')[1]?.toLowerCase();
    return ethiopianUniversities.includes(domain);
}

/**
 * Validate password strength
 * @param {string} password - Password to validate
 * @returns {Object} { valid, strength, message, score }
 */
function validatePasswordStrength(password) {
    let score = 0;
    let messages = [];
    
    if (password.length >= 8) {
        score++;
    } else {
        messages.push('At least 8 characters');
    }
    
    if (/[a-z]/.test(password)) {
        score++;
    } else {
        messages.push('Lowercase letter');
    }
    
    if (/[A-Z]/.test(password)) {
        score++;
    } else {
        messages.push('Uppercase letter');
    }
    
    if (/[0-9]/.test(password)) {
        score++;
    } else {
        messages.push('Number');
    }
    
    if (/[^A-Za-z0-9]/.test(password)) {
        score++;
    } else {
        messages.push('Special character');
    }
    
    let strength, message;
    if (score >= 5) {
        strength = 'strong';
        message = '✅ Strong password!';
    } else if (score >= 4) {
        strength = 'good';
        message = '👍 Good password';
    } else if (score >= 3) {
        strength = 'fair';
        message = '⚠️ Fair password';
    } else {
        strength = 'weak';
        message = '❌ Weak password';
    }
    
    return {
        valid: score >= 4,
        strength: strength,
        message: message,
        score: score,
        requirements: messages
    };
}

/**
 * Validate Ethiopian phone number
 * @param {string} phone - Phone number to validate
 * @returns {boolean}
 */
function validateEthiopianPhone(phone) {
    // Remove spaces, dashes, parentheses
    const clean = phone.replace(/[\s\-\(\)]/g, '');
    // Ethiopian phone patterns: 09XXXXXXXX, +2519XXXXXXXX, 2519XXXXXXXX
    const regex = /^(09|2519|\+2519)[0-9]{8}$/;
    return regex.test(clean);
}

/**
 * Validate international phone number
 * @param {string} phone - Phone number to validate
 * @returns {boolean}
 */
function validatePhone(phone) {
    // Remove spaces, dashes, parentheses, plus
    const clean = phone.replace(/[\s\-\(\)]/g, '');
    // Basic international phone validation
    const regex = /^\+?[0-9]{7,15}$/;
    return regex.test(clean);
}

/**
 * Validate CV file upload
 * @param {File} file - File object to validate
 * @returns {Object} { valid, message }
 */
function validateCVFile(file) {
    if (!file) {
        return { valid: false, message: 'No file selected.' };
    }
    
    if (!CONFIG.ALLOWED_FILE_TYPES.includes(file.type)) {
        return { valid: false, message: 'Please upload a PDF file only.' };
    }
    
    if (file.size > CONFIG.MAX_FILE_SIZE) {
        return { 
            valid: false, 
            message: `File size must be less than ${formatBytes(CONFIG.MAX_FILE_SIZE)}.` 
        };
    }
    
    return { valid: true, message: 'File is valid.' };
}

/**
 * Format bytes to human readable format
 * @param {number} bytes - Bytes to format
 * @returns {string}
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ============================================
// UI HELPER FUNCTIONS
// ============================================

/**
 * Show error message for a form field
 * @param {string} fieldId - ID of the form field
 * @param {string} message - Error message to display
 */
function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Remove existing error
    removeError(fieldId);
    
    // Add error class to field
    field.classList.add('error');
    field.classList.remove('success');
    
    // Create error message element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'validation-feedback error';
    errorDiv.id = `${fieldId}-error`;
    errorDiv.textContent = message;
    
    // Insert after field
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
}

/**
 * Show success message for a form field
 * @param {string} fieldId - ID of the form field
 * @param {string} message - Success message to display
 */
function showSuccess(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Remove existing
    removeError(fieldId);
    
    // Add success class
    field.classList.remove('error');
    field.classList.add('success');
    
    // Create success message
    const successDiv = document.createElement('div');
    successDiv.className = 'validation-feedback success';
    successDiv.id = `${fieldId}-success`;
    successDiv.textContent = message || '✓ Looks good!';
    
    field.parentNode.insertBefore(successDiv, field.nextSibling);
}

/**
 * Remove error/success message for a field
 * @param {string} fieldId - ID of the form field
 */
function removeError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    field.classList.remove('error', 'success');
    
    // Remove error message
    const errorEl = document.getElementById(`${fieldId}-error`);
    if (errorEl) errorEl.remove();
    
    // Remove success message
    const successEl = document.getElementById(`${fieldId}-success`);
    if (successEl) successEl.remove();
}

/**
 * Clear all error messages in a form
 * @param {string} formId - ID of the form
 */
function clearAllErrors(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const inputs = form.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.classList.remove('error', 'success');
        const errorEl = document.getElementById(`${input.id}-error`);
        if (errorEl) errorEl.remove();
        const successEl = document.getElementById(`${input.id}-success`);
        if (successEl) successEl.remove();
    });
}

/**
 * Display flash message
 * @param {string} type - success, error, warning, info
 * @param {string} message - Message to display
 */
function showFlashMessage(type, message) {
    const container = document.querySelector('.flash-container');
    if (!container) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible`;
    alert.innerHTML = `
        <span class="alert-icon">${getAlertIcon(type)}</span>
        <span>${message}</span>
        <button class="alert-close">&times;</button>
    `;
    
    container.appendChild(alert);
    
    // Auto-dismiss
    setTimeout(() => {
        dismissAlert(alert);
    }, CONFIG.AUTO_HIDE_ALERT_DELAY);
    
    // Click to dismiss
    alert.querySelector('.alert-close').addEventListener('click', function() {
        dismissAlert(alert);
    });
}

/**
 * Get alert icon based on type
 * @param {string} type - Alert type
 * @returns {string}
 */
function getAlertIcon(type) {
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    return icons[type] || '📌';
}

/**
 * Dismiss an alert with animation
 * @param {HTMLElement} alert - Alert element
 */
function dismissAlert(alert) {
    alert.style.transition = 'opacity 0.5s ease, transform 0.3s ease';
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-20px)';
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 500);
}

/**
 * Auto-hide all alerts after delay
 */
function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            dismissAlert(alert);
        }, CONFIG.AUTO_HIDE_ALERT_DELAY);
    });
}

// ============================================
// FORM VALIDATION FUNCTIONS
// ============================================

/**
 * Validate registration form
 * @param {string} formId - ID of the form
 * @returns {boolean}
 */
function validateRegistrationForm(formId = 'registrationForm') {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const phone = document.getElementById('phone');
    const role = document.getElementById('role');
    const agreeTerms = document.querySelector('input[name="agree_terms"]');
    
    let isValid = true;
    
    // Clear previous messages
    clearAllErrors(formId);
    
    // Validate name
    if (!name.value.trim()) {
        showError('name', 'Full name is required.');
        isValid = false;
    } else if (name.value.trim().length < 2) {
        showError('name', 'Name must be at least 2 characters.');
        isValid = false;
    } else if (name.value.trim().length > 100) {
        showError('name', 'Name is too long (max 100 characters).');
        isValid = false;
    } else {
        showSuccess('name');
    }
    
    // Validate email
    if (!email.value.trim()) {
        showError('email', 'Email address is required.');
        isValid = false;
    } else if (!validateEmail(email.value)) {
        showError('email', 'Please enter a valid email address.');
        isValid = false;
    } else {
        // Check if Ethiopian university email
        if (isEthiopianUniversityEmail(email.value)) {
            showSuccess('email', '✓ Ethiopian university email detected');
        } else {
            showSuccess('email');
        }
    }
    
    // Validate role
    if (!role || !role.value) {
        showError('role', 'Please select your role.');
        isValid = false;
    } else {
        showSuccess('role');
    }
    
    // Validate password
    if (!password.value) {
        showError('password', 'Password is required.');
        isValid = false;
    } else {
        const strength = validatePasswordStrength(password.value);
        if (!strength.valid) {
            showError('password', `Password needs: ${strength.requirements.join(', ')}`);
            isValid = false;
        } else {
            showSuccess('password', strength.message);
        }
    }
    
    // Validate confirm password
    if (confirmPassword && confirmPassword.value) {
        if (confirmPassword.value !== password.value) {
            showError('confirm_password', 'Passwords do not match.');
            isValid = false;
        } else {
            showSuccess('confirm_password');
        }
    }
    
    // Validate phone (optional)
    if (phone && phone.value.trim()) {
        if (!validatePhone(phone.value)) {
            showError('phone', 'Please enter a valid phone number.');
            isValid = false;
        } else if (validateEthiopianPhone(phone.value)) {
            showSuccess('phone', '✓ Ethiopian phone number');
        } else {
            showSuccess('phone');
        }
    }
    
    // Validate terms
    if (agreeTerms && !agreeTerms.checked) {
        showError('agree_terms', 'You must agree to the Terms of Service.');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Validate login form
 * @param {string} formId - ID of the form
 * @returns {boolean}
 */
function validateLoginForm(formId = 'loginForm') {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    
    let isValid = true;
    
    clearAllErrors(formId);
    
    if (!email.value.trim()) {
        showError('email', 'Email is required.');
        isValid = false;
    } else if (!validateEmail(email.value)) {
        showError('email', 'Please enter a valid email address.');
        isValid = false;
    } else {
        showSuccess('email');
    }
    
    if (!password.value) {
        showError('password', 'Password is required.');
        isValid = false;
    } else {
        showSuccess('password');
    }
    
    return isValid;
}

/**
 * Validate job posting form
 * @param {string} formId - ID of the form
 * @returns {boolean}
 */
function validateJobForm(formId = 'jobForm') {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const title = document.getElementById('title');
    const description = document.getElementById('description');
    const location = document.getElementById('location');
    const jobType = document.getElementById('job_type');
    const deadline = document.getElementById('application_deadline');
    
    let isValid = true;
    
    clearAllErrors(formId);
    
    if (!title.value.trim()) {
        showError('title', 'Job title is required.');
        isValid = false;
    } else if (title.value.trim().length < 3) {
        showError('title', 'Job title must be at least 3 characters.');
        isValid = false;
    } else {
        showSuccess('title');
    }
    
    if (!description.value.trim()) {
        showError('description', 'Job description is required.');
        isValid = false;
    } else if (description.value.trim().length < 20) {
        showError('description', 'Description must be at least 20 characters.');
        isValid = false;
    } else {
        showSuccess('description');
    }
    
    if (!location.value.trim()) {
        showError('location', 'Job location is required.');
        isValid = false;
    } else {
        showSuccess('location');
    }
    
    if (jobType && !jobType.value) {
        showError('job_type', 'Please select a job type.');
        isValid = false;
    } else if (jobType) {
        showSuccess('job_type');
    }
    
    if (deadline && deadline.value) {
        const selectedDate = new Date(deadline.value);
        const today = new Date();
        if (selectedDate < today) {
            showError('application_deadline', 'Deadline must be in the future.');
            isValid = false;
        } else {
            showSuccess('application_deadline');
        }
    }
    
    return isValid;
}

/**
 * Validate profile form
 * @param {string} formId - ID of the form
 * @returns {boolean}
 */
function validateProfileForm(formId = 'profileForm') {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const role = form.dataset.role || '';
    let isValid = true;
    
    clearAllErrors(formId);
    
    if (role === 'employer') {
        const companyName = document.getElementById('company_name');
        if (companyName && !companyName.value.trim()) {
            showError('company_name', 'Company name is required.');
            isValid = false;
        } else if (companyName) {
            showSuccess('company_name');
        }
    } else if (role === 'graduate') {
        const university = document.getElementById('university');
        const graduationYear = document.getElementById('graduation_year');
        
        if (university && !university.value.trim()) {
            showError('university', 'University name is required.');
            isValid = false;
        } else if (university) {
            showSuccess('university');
        }
        
        if (graduationYear && !graduationYear.value) {
            showError('graduation_year', 'Graduation year is required.');
            isValid = false;
        } else if (graduationYear) {
            showSuccess('graduation_year');
        }
    }
    
    return isValid;
}

// ============================================
// PASSWORD STRENGTH INDICATOR
// ============================================

/**
 * Update password strength indicator
 * @param {string} password - Password to check
 */
function updatePasswordStrength(password) {
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text span');
    const requirementsList = document.querySelector('.requirements-list');
    
    if (!strengthBar || !strengthText) return;
    
    const result = validatePasswordStrength(password);
    
    // Update bar
    strengthBar.className = 'strength-bar ' + result.strength;
    
    // Update text
    strengthText.textContent = result.message;
    strengthText.style.color = 
        result.strength === 'strong' ? '#078930' :
        result.strength === 'good' ? '#3498db' :
        result.strength === 'fair' ? '#f39c12' : '#da121a';
    
    // Update requirements list if it exists
    if (requirementsList) {
        const items = requirementsList.querySelectorAll('li');
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        
        items.forEach(item => {
            const key = item.dataset.requirement;
            if (checks[key] !== undefined) {
                item.textContent = (checks[key] ? '✅ ' : '❌ ') + item.textContent.replace(/^[✅❌]\s*/, '');
                item.style.color = checks[key] ? '#078930' : '#da121a';
            }
        });
    }
}

// ============================================
// CHARACTER COUNTER
// ============================================

/**
 * Add character counter to a textarea
 * @param {string} textareaId - ID of the textarea
 * @param {number} maxLength - Maximum character length
 */
function addCharacterCounter(textareaId, maxLength) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    
    // Check if counter already exists
    let counter = textarea.parentNode.querySelector('.character-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.className = 'character-counter';
        counter.style.textAlign = 'right';
        counter.style.fontSize = '0.8rem';
        counter.style.color = 'var(--dark-gray)';
        counter.style.marginTop = '4px';
        textarea.parentNode.appendChild(counter);
    }
    
    const updateCounter = () => {
        const remaining = maxLength - textarea.value.length;
        counter.textContent = `${textarea.value.length} / ${maxLength} characters`;
        
        if (remaining < 0) {
            counter.style.color = 'var(--ethiopian-red)';
        } else if (remaining < 50) {
            counter.style.color = 'var(--warning)';
        } else {
            counter.style.color = 'var(--dark-gray)';
        }
    };
    
    textarea.addEventListener('input', updateCounter);
    textarea.addEventListener('change', updateCounter);
    updateCounter();
}

// ============================================
// REAL-TIME VALIDATION
// ============================================

/**
 * Setup real-time validation for a form field
 * @param {string} fieldId - ID of the field
 * @param {Function} validator - Validation function
 */
function setupRealtimeValidation(fieldId, validator) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    let debounceTimer;
    
    field.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const result = validator(this.value);
            if (result.valid) {
                showSuccess(fieldId, result.message || '✓ Valid');
            } else {
                showError(fieldId, result.message || 'Invalid input');
            }
        }, CONFIG.DEBOUNCE_DELAY);
    });
    
    field.addEventListener('blur', function() {
        clearTimeout(debounceTimer);
        if (this.value) {
            const result = validator(this.value);
            if (result.valid) {
                showSuccess(fieldId, result.message || '✓ Valid');
            } else {
                showError(fieldId, result.message || 'Invalid input');
            }
        }
    });
}

// ============================================
// MOBILE MENU TOGGLE
// ============================================

/**
 * Initialize mobile menu toggle
 */
function initMobileMenu() {
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.querySelector('.nav-menu');
    
    if (!toggle || !menu) return;
    
    toggle.addEventListener('click', function() {
        menu.classList.toggle('active');
        const expanded = menu.classList.contains('active');
        toggle.setAttribute('aria-expanded', expanded);
        toggle.setAttribute('aria-label', expanded ? 'Close menu' : 'Open menu');
    });
    
    // Close menu on outside click
    document.addEventListener('click', function(e) {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open menu');
        }
    });
}

// ============================================
// JOB APPLICATION HANDLING
// ============================================

/**
 * Handle job application submit
 * @param {Event} event - Form submit event
 * @returns {boolean}
 */
function handleJobApplication(event) {
    const form = event.target;
    const cvFile = document.getElementById('cv_file');
    
    if (cvFile && cvFile.files.length > 0) {
        const validation = validateCVFile(cvFile.files[0]);
        if (!validation.valid) {
            showError('cv_file', validation.message);
            event.preventDefault();
            return false;
        }
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Processing...';
        submitBtn.classList.add('loading');
    }
    
    return true;
}

// ============================================
// CONFIRM DIALOGS
// ============================================

/**
 * Show confirm dialog for delete actions
 * @param {string} itemName - Name of item to delete
 * @param {string} additionalInfo - Additional info
 * @returns {boolean}
 */
function confirmDelete(itemName = 'this item', additionalInfo = '') {
    const message = `Are you sure you want to delete "${itemName}"?\n\nThis action cannot be undone.${additionalInfo ? '\n\n' + additionalInfo : ''}`;
    return confirm(message);
}

/**
 * Show confirm dialog for bulk actions
 * @param {number} count - Number of items
 * @param {string} action - Action name
 * @returns {boolean}
 */
function confirmBulkAction(count, action) {
    return confirm(`Are you sure you want to ${action} ${count} item(s)?\n\nThis action cannot be undone.`);
}

// ============================================
// TABLE SELECT ALL
// ============================================

/**
 * Toggle select all checkboxes in a table
 * @param {string} tableId - ID of the table
 * @param {string} checkboxClass - Class of checkboxes
 */
function toggleSelectAll(tableId, checkboxClass = 'item-checkbox') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const selectAll = table.querySelector('.select-all');
    if (!selectAll) return;
    
    const checkboxes = table.querySelectorAll(`.${checkboxClass}`);
    const isChecked = selectAll.checked;
    
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
    });
    
    updateSelectedCount(tableId, checkboxClass);
}

/**
 * Update selected count display
 * @param {string} tableId - ID of the table
 * @param {string} checkboxClass - Class of checkboxes
 */
function updateSelectedCount(tableId, checkboxClass = 'item-checkbox') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const checkboxes = table.querySelectorAll(`.${checkboxClass}:checked`);
    const countDisplay = table.querySelector('.selected-count');
    if (countDisplay) {
        countDisplay.textContent = checkboxes.length;
    }
}

// ============================================
// FILE UPLOAD PREVIEW
// ============================================

/**
 * Setup file upload preview
 * @param {string} fileInputId - ID of file input
 * @param {string} previewId - ID of preview element
 */
function setupFileUploadPreview(fileInputId, previewId) {
    const fileInput = document.getElementById(fileInputId);
    const preview = document.getElementById(previewId);
    
    if (!fileInput || !preview) return;
    
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        
        if (!file) {
            preview.innerHTML = '';
            preview.style.display = 'none';
            return;
        }
        
        const validation = validateCVFile(file);
        if (!validation.valid) {
            showError(fileInputId, validation.message);
            preview.innerHTML = '';
            preview.style.display = 'none';
            return;
        }
        
        // Show success
        showSuccess(fileInputId, validation.message);
        
        // Display file info
        preview.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: var(--light-gray); border-radius: var(--radius);">
                <span style="font-size: 2rem;">📄</span>
                <div>
                    <div style="font-weight: 600;">${file.name}</div>
                    <div style="font-size: 0.85rem; color: var(--dark-gray);">
                        ${formatBytes(file.size)} • PDF
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeFile('${fileInputId}')">✕</button>
            </div>
        `;
        preview.style.display = 'block';
    });
}

/**
 * Remove selected file
 * @param {string} fileInputId - ID of file input
 */
function removeFile(fileInputId) {
    const fileInput = document.getElementById(fileInputId);
    if (!fileInput) return;
    
    fileInput.value = '';
    removeError(fileInputId);
    
    const preview = document.getElementById(`${fileInputId}-preview`);
    if (preview) {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
}

// ============================================
// PAGE INITIALIZATION
// ============================================

/**
 * Initialize all page functions
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Graduate Job Connect initialized successfully!');
    
    // Auto-hide alerts
    autoHideAlerts();
    
    // Initialize mobile menu
    initMobileMenu();
    
    // Setup character counters
    addCharacterCounter('description', CONFIG.MAX_DESCRIPTION_LENGTH);
    addCharacterCounter('company_description', CONFIG.MAX_DESCRIPTION_LENGTH);
    addCharacterCounter('skills', CONFIG.MAX_SKILLS_LENGTH);
    addCharacterCounter('experience', CONFIG.MAX_SKILLS_LENGTH);
    addCharacterCounter('cover_letter', CONFIG.MAX_DESCRIPTION_LENGTH);
    addCharacterCounter('requirements', CONFIG.MAX_DESCRIPTION_LENGTH);
    
    // Setup password strength indicator
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
    }
    
    // Setup file upload preview
    setupFileUploadPreview('cv_file', 'cv_preview');
    setupFileUploadPreview('logo_file', 'logo_preview');
    
    // Setup real-time email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        setupRealtimeValidation('email', function(value) {
            if (!value) return { valid: false, message: 'Email is required.' };
            if (!validateEmail(value)) return { valid: false, message: 'Invalid email format.' };
            
            let message = '✓ Valid email';
            if (isEthiopianUniversityEmail(value)) {
                message = '✓ Ethiopian university email detected 🎓';
            }
            return { valid: true, message: message };
        });
    }
    
    // Setup real-time phone validation
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        setupRealtimeValidation('phone', function(value) {
            if (!value) return { valid: true, message: 'Optional' };
            if (!validatePhone(value)) return { valid: false, message: 'Invalid phone format.' };
            
            let message = '✓ Valid phone number';
            if (validateEthiopianPhone(value)) {
                message = '✓ Ethiopian phone number 🇪🇹';
            }
            return { valid: true, message: message };
        });
    }
    
    // Setup real-time password confirmation
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword && passwordInput) {
        confirmPassword.addEventListener('input', function() {
            if (!this.value) {
                removeError('confirm_password');
                return;
            }
            if (this.value !== passwordInput.value) {
                showError('confirm_password', 'Passwords do not match.');
            } else {
                showSuccess('confirm_password', '✓ Passwords match');
            }
        });
    }
    
    // Form submissions
    const registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            if (!validateRegistrationForm()) {
                e.preventDefault();
            }
        });
    }
    
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateLoginForm()) {
                e.preventDefault();
            }
        });
    }
    
    const jobForm = document.getElementById('jobForm');
    if (jobForm) {
        jobForm.addEventListener('submit', function(e) {
            if (!validateJobForm()) {
                e.preventDefault();
            }
        });
    }
    
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (!validateProfileForm()) {
                e.preventDefault();
            }
        });
    }
    
    const applicationForm = document.getElementById('applicationForm');
    if (applicationForm) {
        applicationForm.addEventListener('submit', handleJobApplication);
    }
    
    // Delete confirmations
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            const itemName = this.dataset.itemName || 'this item';
            if (!confirmDelete(itemName)) {
                e.preventDefault();
            }
        });
    });
    
    // Toggle status confirmations
    document.querySelectorAll('.btn-toggle-status').forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.dataset.action || 'change status of';
            if (!confirm(`Are you sure you want to ${action}?`)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-dismiss alerts on close button click
    document.querySelectorAll('.alert-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) dismissAlert(alert);
        });
    });
    
    console.log('✅ All components initialized successfully!');
});

// ============================================
// EXPOSE FUNCTIONS FOR INLINE USE
// ============================================
window.validateRegistrationForm = validateRegistrationForm;
window.validateLoginForm = validateLoginForm;
window.validateJobForm = validateJobForm;
window.validateProfileForm = validateProfileForm;
window.confirmDelete = confirmDelete;
window.confirmBulkAction = confirmBulkAction;
window.toggleSelectAll = toggleSelectAll;
window.updateSelectedCount = updateSelectedCount;
window.removeFile = removeFile;
window.showFlashMessage = showFlashMessage;