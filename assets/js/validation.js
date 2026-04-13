// Form validation functions

// Validate email format
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate password strength
function validatePassword(password) {
    return password.length >= 6;
}

// Validate file upload (PDF only)
function validateFile(file) {
    if (!file) return false;
    const allowedTypes = ['application/pdf'];
    return allowedTypes.includes(file.type);
}

// Show error message
function showError(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = message;
        element.classList.remove('hidden');
    }
}

// Hide error message
function hideError(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.classList.add('hidden');
    }
}

// Validate registration form
function validateRegistrationForm() {
    let isValid = true;

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    hideError('email-error');
    hideError('password-error');
    hideError('confirm-password-error');

    if (!validateEmail(email)) {
        showError('email-error', 'Please enter a valid email address.');
        isValid = false;
    }

    if (!validatePassword(password)) {
        showError('password-error', 'Password must be at least 6 characters long.');
        isValid = false;
    }

    if (password !== confirmPassword) {
        showError('confirm-password-error', 'Passwords do not match.');
        isValid = false;
    }

    return isValid;
}

// Validate login form
function validateLoginForm() {
    let isValid = true;

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    hideError('email-error');
    hideError('password-error');

    if (!validateEmail(email)) {
        showError('email-error', 'Please enter a valid email address.');
        isValid = false;
    }

    if (!password) {
        showError('password-error', 'Please enter your password.');
        isValid = false;
    }

    return isValid;
}

// Validate upload form
function validateUploadForm() {
    let isValid = true;

    const title = document.getElementById('title').value;
    const moduleCode = document.getElementById('module_code').value;
    const university = document.getElementById('university').value;
    const price = document.getElementById('price').value;
    const file = document.getElementById('pdf_file').files[0];

    hideError('title-error');
    hideError('module-error');
    hideError('university-error');
    hideError('price-error');
    hideError('file-error');

    if (!title.trim()) {
        showError('title-error', 'Title is required.');
        isValid = false;
    }

    if (!moduleCode.trim()) {
        showError('module-error', 'Module code is required.');
        isValid = false;
    }

    if (!university.trim()) {
        showError('university-error', 'University is required.');
        isValid = false;
    }

    if (!price || isNaN(price) || parseFloat(price) <= 0) {
        showError('price-error', 'Please enter a valid price.');
        isValid = false;
    }

    if (!file || !validateFile(file)) {
        showError('file-error', 'Please select a valid PDF file.');
        isValid = false;
    }

    return isValid;
}