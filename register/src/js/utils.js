// Utility functions for the registration system

/**
 * Generate a random string for special registration links
 * @param {number} length - Length of the random string
 * @returns {string} - Random string
 */
export function generateRandomString(length = 16) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

/**
 * Validate form data
 * @param {FormData} formData - Form data from the registration form
 * @returns {Object} - Validation result
 */
export function validateForm(formData) {
  const errors = {};

  // Required fields
  const requiredFields = [
    { name: 'meno', label: 'Meno' },
    { name: 'priezvisko', label: 'Priezvisko' },
    { name: 'email', label: 'Email' },
    { name: 'vek', label: 'Vek' },
    { name: 'pohlavie', label: 'Pohlavie' },
    { name: 'ubytovanie_id', label: 'Ubytovanie' },
    { name: 'gdpr', label: 'GDPR súhlas' }
  ];

  for (const field of requiredFields) {
    const value = formData.get(field.name);
    if (!value) {
      errors[field.name] = `${field.label} je povinné pole.`;
    }
  }

  // Email validation
  const email = formData.get('email');
  if (email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      errors.email = 'Neplatný formát emailu.';
    }
  }

  // Age validation (14-26 years)
  const vek = parseInt(formData.get('vek'));
  if (!isNaN(vek)) {
    const currentYear = new Date().getFullYear();
    // They must be at least 14 this year and cannot be 27 or older this year
    if (vek < 14 || (vek - 14 + currentYear) > (currentYear + 13)) {
      errors.vek = 'Vek musí byť 14-26 rokov v tomto roku.';
    }
  }

  // Youth group validation
  const mladez_id = formData.get('mladez_id');
  const vlastny_mladez = formData.get('vlastny_mladez');
  if (mladez_id === 'iny' && !vlastny_mladez) {
    errors.vlastny_mladez = 'Zadajte názov vášho spoločenstva.';
  }

  // Validate activities - at least one activity per day should be selected
  const activities = formData.getAll('aktivity');
  if (activities.length === 0) {
    errors.aktivity = 'Vyberte aspoň jednu aktivitu.';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors
  };
}

/**
 * Format date
 * @param {Date} date - Date to format
 * @returns {string} - Formatted date
 */
export function formatDate(date) {
  const options = {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  };
  return date.toLocaleDateString('sk-SK', options);
}

/**
 * Sanitize user input to prevent XSS
 * @param {string} input - User input
 * @returns {string} - Sanitized input
 */
export function sanitizeInput(input) {
  if (!input) return '';
  return input
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Create error message element
 * @param {string} message - Error message
 * @returns {HTMLElement} - Error message element
 */
export function createErrorMessage(message) {
  const errorElement = document.createElement('div');
  errorElement.className = 'error-message';
  errorElement.textContent = message;
  return errorElement;
}

/**
 * Show error message under an input element
 * @param {HTMLElement} inputElement - Input element
 * @param {string} message - Error message
 */
export function showInputError(inputElement, message) {
  // Remove existing error message
  const existingError = inputElement.parentNode.querySelector('.error-message');
  if (existingError) {
    existingError.remove();
  }

  // Add new error message
  const errorElement = createErrorMessage(message);
  inputElement.parentNode.appendChild(errorElement);
  inputElement.classList.add('error');
}

/**
 * Clear all form errors
 * @param {HTMLFormElement} form - Form element
 */
export function clearFormErrors(form) {
  // Remove error messages
  const errorMessages = form.querySelectorAll('.error-message');
  errorMessages.forEach(element => element.remove());

  // Remove error class from inputs
  const errorInputs = form.querySelectorAll('.error');
  errorInputs.forEach(element => element.classList.remove('error'));
}

/**
 * Show form errors
 * @param {HTMLFormElement} form - Form element
 * @param {Object} errors - Errors object
 */
export function showFormErrors(form, errors) {
  clearFormErrors(form);

  for (const [field, message] of Object.entries(errors)) {
    const inputElement = form.querySelector(`[name="${field}"]`);
    if (inputElement) {
      showInputError(inputElement, message);
    }
  }

  // Scroll to the first error
  const firstErrorElement = form.querySelector('.error');
  if (firstErrorElement) {
    firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

export default {
  generateRandomString,
  validateForm,
  formatDate,
  sanitizeInput,
  createErrorMessage,
  showInputError,
  clearFormErrors,
  showFormErrors
};
