// API handlers for the registration system
import db from './db.js';

/**
 * Process registration form data
 * @param {FormData} formData - Form data from the registration form
 * @returns {Promise} - Registration result
 */
export async function processRegistration(formData) {
  try {
    // Validate required fields
    const requiredFields = ['meno', 'priezvisko', 'email', 'vek', 'pohlavie', 'gdpr'];
    for (const field of requiredFields) {
      if (!formData.get(field)) {
        throw new Error(`Field ${field} is required`);
      }
    }

    // Validate email format
    const email = formData.get('email');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      throw new Error('Invalid email format');
    }

    // Validate age (14-26 years)
    const vek = parseInt(formData.get('vek'));
    const currentYear = new Date().getFullYear();
    if (isNaN(vek) || vek < 14 || (vek - 14 + currentYear) > (currentYear + 13)) {
      throw new Error('Age must be between 14 and 26 years (this year)');
    }

    // Prepare data object for registration
    const data = {
      meno: formData.get('meno'),
      priezvisko: formData.get('priezvisko'),
      email: formData.get('email'),
      vek: vek,
      pohlavie: formData.get('pohlavie'),
      mladez_id: formData.get('mladez_id') || null,
      vlastny_mladez: formData.get('vlastny_mladez') || null,
      prvy_krat: formData.get('prvy_krat') === 'on',
      poznamka: formData.get('poznamka') || null,
      gdpr: formData.get('gdpr') === 'on',
      typ: formData.get('typ') || 'ucastnik',
      ubytovanie_id: formData.get('ubytovanie_id') || null
    };

    // Get activities
    const aktivity = formData.getAll('aktivity');
    if (aktivity.length > 0) {
      data.aktivity = aktivity.map(id => parseInt(id));
    }

    // Get allergies
    const alergie = formData.getAll('alergie');
    if (alergie.length > 0) {
      data.alergie = alergie.map(id => parseInt(id));
    }

    // Get custom allergies
    data.vlastne_alergie = formData.get('vlastne_alergie') || null;

    // Register participant
    return await db.registerParticipant(data);
  } catch (error) {
    console.error('Registration processing error:', error);
    return {
      success: false,
      error: error.message || 'An error occurred during registration'
    };
  }
}

/**
 * Get all available activities
 * @returns {Promise} - Activities data
 */
export async function getActivities() {
  try {
    return await db.getAvailableActivities();
  } catch (error) {
    console.error('Error getting activities:', error);
    return [];
  }
}

/**
 * Get available accommodations based on gender and registration type
 * @param {string} pohlavie - Gender ('muz' or 'zena')
 * @param {string} typ - Registration type ('ucastnik', 'veduci', or 'host')
 * @returns {Promise} - Accommodations data
 */
export async function getAccommodations(pohlavie, typ) {
  try {
    return await db.getAvailableAccommodation(pohlavie, typ);
  } catch (error) {
    console.error('Error getting accommodations:', error);
    return [];
  }
}

/**
 * Get all youth groups
 * @returns {Promise} - Youth groups data
 */
export async function getYouthGroups() {
  try {
    return await db.getYouthGroups();
  } catch (error) {
    console.error('Error getting youth groups:', error);
    return [];
  }
}

/**
 * Get all allergies
 * @returns {Promise} - Allergies data
 */
export async function getAllergies() {
  try {
    return await db.getAllergies();
  } catch (error) {
    console.error('Error getting allergies:', error);
    return [];
  }
}

export default {
  processRegistration,
  getActivities,
  getAccommodations,
  getYouthGroups,
  getAllergies
};
