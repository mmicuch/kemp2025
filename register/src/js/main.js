// Main JavaScript file for the registration system
import utils from './utils.js';

// Fix API base URL to point to the direct connector
const API_BASE_URL = 'api-direct.php?endpoint=';
window.API_BASE_URL = API_BASE_URL; // Expose API_BASE_URL globally

// Debug mode - set to false in production
const DEBUG_API = false;

/**
 * Enhanced fetch function with better error handling
 * @param {string} url - The URL to fetch
 * @param {Object} options - Fetch options
 * @returns {Promise} - Fetch response
 */
async function fetchWithErrorHandling(url, options = {}) {
  try {
    const response = await fetch(url, options);

    if (!response.ok) {
      // Try to get error details from response
      let errorDetails = '';
      try {
        const errorData = await response.clone().json();
        errorDetails = JSON.stringify(errorData);
      } catch (e) {
        try {
          errorDetails = await response.clone().text();
        } catch (e2) {
          errorDetails = 'Could not extract error details';
        }
      }

      console.error(`API Error (${response.status}): ${errorDetails}`);
      throw new Error(`API request failed: ${response.status} ${response.statusText}`);
    }

    // Try to parse JSON, but handle non-JSON responses gracefully
    try {
      const data = await response.json();
      return data;
    } catch (jsonError) {
      console.error('JSON Parse Error:', jsonError);

      // Get the raw response text
      const rawResponse = await response.clone().text();

      // Check if the response is empty or not valid JSON
      if (!rawResponse || rawResponse.trim() === '') {
        throw new Error('Empty response from server');
      }

      // Check if this might be HTML error page
      if (rawResponse.includes('<!DOCTYPE html>') || rawResponse.includes('<html>')) {
        throw new Error('Server returned HTML instead of JSON. This might be a server error page.');
      }

      // Return a formatted error response
      return {
        success: false,
        error: 'Server returned invalid JSON'
      };
    }
  } catch (error) {
    console.error('API Request Error:', error);
    throw error;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Get form elements right at the beginning so they're available for all functions
  const form = document.getElementById('registration-form');
  const mladezSelect = document.getElementById('mladez');
  const vlastnyMladezContainer = document.getElementById('vlastny-mladez-container');
  const genderInputs = document.querySelectorAll('input[name="pohlavie"]');
  const ubytovanieSelect = document.getElementById('ubytovanie');
  const submitButton = document.getElementById('submit-button');
  const errorContainer = document.getElementById('error-container');
  const successMessage = document.getElementById('success-message');
  const jeHostCheckbox = document.getElementById('je-host');
  const ubytovanieSekcia = document.getElementById('ubytovanie-sekcia');
  const aktivitySekcia = document.getElementById('aktivity-sekcia');
  const poznamkaLabel = document.getElementById('poznamka-label');

  // Check URL parameters for special registration and tokens
  const urlParams = new URLSearchParams(window.location.search);
  const isSpecialReg = urlParams.get('special') === 'true';
  const token = urlParams.get('token') || '';
  let regType = 'ucastnik'; // Default registration type

  console.log("URL parameters:", {
    special: urlParams.get('special'),
    token: token,
    type: urlParams.get('type'),
    isSpecialReg: isSpecialReg
  });

  // Validate token format if present (should be UUID v4)
  if (token) {
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    if (!uuidRegex.test(token)) {
      // Invalid token format
      console.log("Invalid token format:", token);
      showTokenError();
      return;
    }

    console.log("Valid token detected:", token);

    // Extract type from token first character (for demonstration)
    // In a real-world scenario, you'd validate this token against the server
    if (isSpecialReg) {
      regType = 'veduci'; // Default for special registrations
      console.log("Setting registration type to veduci by default");

      document.querySelector('.registration-title').textContent = 'Špeciálna registrácia';

      // If URL contains token parameter, setup special registration for guest
      if (token) {
        // Check if "host" is in the token URL
        if (urlParams.get('type') === 'host') {
          regType = 'host';
          console.log("Host detected in URL, setting up host registration");
          document.querySelector('.registration-title').textContent = 'Registrácia hosťa';

          // Add host-registration class to body for CSS hiding of sections
          document.body.classList.add('host-registration');

          // Setup special registration for guest - hide activities, allergies, accommodation
          console.log("Calling setupGuestRegistration");
          setupGuestRegistration();
        }
      }
    }
  }

  // Store registration type in hidden field
  const typInput = document.querySelector('input[name="typ"]');
  if (typInput) {
    typInput.value = regType;
  }

  // Show token error and redirect
  function showTokenError() {
    const mainElement = document.querySelector('main');
    if (mainElement) {
      mainElement.innerHTML = `
        <div class="error-message" style="display: block; margin: 20px auto; max-width: 600px; text-align: center;">
          <h2>Neplatný registračný odkaz</h2>
          <p>Odkaz, ktorý ste použili, je neplatný alebo expiroval.</p>
          <p><a href="index.html">Späť na hlavnú stránku</a></p>
        </div>
      `;
    }
  }

  // Load data from API
  loadYouthGroups();
  loadAllergies();
  loadActivities();

  // Add event listeners for form interactions
  if (mladezSelect) {
    mladezSelect.addEventListener('change', handleMladezChange);
  }

  // Handle gender selection to update accommodation options
  genderInputs.forEach(input => {
    input.addEventListener('change', handleGenderChange);
  });

  // Handle form submission
  if (form) {
    form.addEventListener('submit', handleFormSubmit);
  }

  /**
   * Setup special guest registration - hide activities, allergies, and accommodation
   */
  function setupGuestRegistration() {
    console.log("Setting up guest registration - hiding unnecessary sections");

    if (!form) {
      console.error("Form element not found!");
      return;
    }

    // Funkcia pre pridanie alebo aktualizáciu skrytého poľa
    function addOrUpdateHiddenField(name, value) {
      let existingField = form.querySelector(`input[type="hidden"][name="${name}"]`);
      if (existingField) {
        existingField.value = value;
        console.log(`Updated existing hidden field ${name} with value ${value}`);
      } else {
        let newField = document.createElement('input');
        newField.type = 'hidden';
        newField.name = name;
        newField.value = value;
        form.appendChild(newField);
        console.log(`Added new hidden field ${name} with value ${value}`);
      }
    }

    // Pridaj skryté polia s defaultnými hodnotami
    addOrUpdateHiddenField('ubytovanie_id', '6');
    addOrUpdateHiddenField('mladez_id', 'iny');
    addOrUpdateHiddenField('vlastny_mladez', 'Host');

    // Najprv nájdeme všetky sekcie, ktoré by mali byť skryté
    const h2Elements = document.querySelectorAll('h2');

    h2Elements.forEach(h2 => {
      const sectionTitle = h2.textContent.trim();
      console.log("Found section with title:", sectionTitle);

      const parentSection = h2.closest('div');

      if (sectionTitle === 'Aktivity' ||
          sectionTitle === 'Mládežnícke spoločenstvo' ||
          sectionTitle === 'Ubytovanie' ||
          sectionTitle === 'Alergie') {

        if (parentSection) {
          console.log("Hiding section:", sectionTitle);
          parentSection.style.display = 'none';

          if (sectionTitle === 'Aktivity') {
            document.querySelectorAll('input[type="radio"][name^="activity-"]').forEach(radio => {
              radio.removeAttribute('required');
            });
          }

          if (sectionTitle === 'Ubytovanie' && ubytovanieSelect) {
            ubytovanieSelect.removeAttribute('required');
          }
        }
      }

      // Handle "Som na kempe prvýkrát" checkbox relocation
      if (sectionTitle === 'Mládežnícke spoločenstvo') {
        const firstTimeCheckbox = document.getElementById('prvy-krat');
        if (firstTimeCheckbox) {
          const checkboxContainer = firstTimeCheckbox.closest('div');
          if (checkboxContainer) {
            const label = checkboxContainer.querySelector('label[for="prvy-krat"]');
            if (label) {
              // Find the personal info section
              const personalInfoSection = Array.from(document.querySelectorAll('h2'))
                .find(el => el.textContent.trim() === 'Osobné údaje');

              if (personalInfoSection) {
                const personalInfoParent = personalInfoSection.closest('div');
                if (personalInfoParent) {
                  // Create new container
                  const newContainer = document.createElement('div');
                  newContainer.className = 'section-youth-group';

                  // Create checkbox wrapper
                  const checkboxWrapper = document.createElement('div');
                  checkboxWrapper.className = 'checkbox-wrapper';
                  
                  // Move checkbox and label to wrapper
                  checkboxWrapper.appendChild(firstTimeCheckbox.cloneNode(true));
                  checkboxWrapper.appendChild(label.cloneNode(true));
                  
                  // Add wrapper to container and insert after personal info
                  newContainer.appendChild(checkboxWrapper);
                  personalInfoParent.after(newContainer);
                  
                  // Remove original checkbox container
                  checkboxContainer.remove();
                }
              }
            }
          }
        }
      }
    });

    // Handle allergies section
    const allergiesContainer = document.getElementById('allergies-container');
    if (allergiesContainer) {
      const allergiesSection = allergiesContainer.closest('div');
      if (allergiesSection) {
        console.log("Hiding allergies section by ID reference");
        allergiesSection.style.display = 'none';
      }
    }

    // Update note label and field
    setTimeout(() => {
      const poznamkaLabel = document.getElementById('poznamka-label');
      if (poznamkaLabel) {
        console.log("Updating note label text for host");
        poznamkaLabel.innerHTML = 'Sem pridajte potrebné informácie pre organizátorov, ako napríklad: ako dlho budete na kempe, či potrebujete nocľah, či máte nejaké potravinové alergie, alebo iné dôležité informácie.';
      }

      const poznamkaField = document.getElementById('poznamka');
      if (poznamkaField) {
        poznamkaField.setAttribute('required', 'required');
        poznamkaField.rows = 5;
        poznamkaField.placeholder = 'Poznámka pre organizátorov';
      }
    }, 100);
  }

  /**
   * Load youth groups from the API
   */
  async function loadYouthGroups() {
    try {
      const data = await fetchWithErrorHandling(`${API_BASE_URL}youth-groups`);
      const groups = data.data || [];

      if (mladezSelect && groups.length > 0) {
        // Clear existing options
        mladezSelect.innerHTML = '';

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Vyberte spoločenstvo...';
        mladezSelect.appendChild(defaultOption);

        // Add required attribute for participants and leaders
        if (regType === 'ucastnik' || regType === 'veduci') {
          mladezSelect.setAttribute('required', 'required');
        }

        // Add options from the database
        groups.forEach(group => {
          const option = document.createElement('option');
          option.value = group.id;
          option.textContent = group.nazov;
          mladezSelect.appendChild(option);
        });

        // Add "Other" option
        const otherOption = document.createElement('option');
        otherOption.value = 'iny';
        otherOption.textContent = 'Iné (zadajte vlastné)';
        mladezSelect.appendChild(otherOption);
      }
    } catch (error) {
      console.error('Failed to load youth groups:', error);
      showError('Nepodarilo sa načítať zoznam mládeží. Skúste obnoviť stránku.');
    }
  }

  /**
   * Load allergies from the API
   */
  async function loadAllergies() {
    try {
      const data = await fetchWithErrorHandling(`${API_BASE_URL}allergies`);
      const allergies = data.data || [];
      const allergiesContainer = document.getElementById('allergies-container');

      if (allergiesContainer && allergies.length > 0) {
        // Clear existing checkboxes
        allergiesContainer.innerHTML = '';

        // Add checkboxes for each allergy
        allergies.forEach(allergy => {
          const checkboxContainer = document.createElement('div');
          checkboxContainer.className = 'allergy-option';

          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.name = 'alergie';
          checkbox.id = `alergia-${allergy.id}`;
          checkbox.value = allergy.id;

          const label = document.createElement('label');
          label.htmlFor = `alergia-${allergy.id}`;
          label.textContent = allergy.nazov;

          checkboxContainer.appendChild(checkbox);
          checkboxContainer.appendChild(label);
          allergiesContainer.appendChild(checkboxContainer);
        });
      }
    } catch (error) {
      console.error('Failed to load allergies:', error);
      showError('Nepodarilo sa načítať zoznam alergií. Skúste obnoviť stránku.');
    }
  }

  /**
   * Load activities from the API
   */
  async function loadActivities() {
    try {
      const data = await fetchWithErrorHandling(`${API_BASE_URL}activities`);
      const activities = data.data || [];
      
      console.log('Activities data:', data);
      
      // Group activities by day
      const activitiesByDay = {
        'streda': [],
        'stvrtok': [],
        'piatok': []
      };
      
      // Naplň existujúce aktivity do príslušných dní
      // Zahrň všetky aktivity, aj tie s available_spots <= 0
      activities.forEach(activity => {
        const day = activity.den.toLowerCase();
        if (activitiesByDay[day] !== undefined) {
          // Zahrň všetky aktivity bez ohľadu na dostupnosť
          activitiesByDay[day].push(activity);
        } else {
          console.log('Neznámy deň:', activity.den, activity);
        }
      });
      
      console.log('Activities by day:', activitiesByDay);
      
      const activitiesContainer = document.getElementById('activities-container');
      if (activitiesContainer) {
        activitiesContainer.innerHTML = '';
        
        // Správne poradie dní
        const dayOrder = ['streda', 'stvrtok', 'piatok'];
        
        // Vytvor sekcie pre všetky dni v správnom poradí
        for (const day of dayOrder) {
          const dayActivities = activitiesByDay[day] || [];
          
          const daySection = document.createElement('div');
          daySection.className = 'day-section';
          
          const dayHeader = document.createElement('div');
          dayHeader.className = 'day-header';
          
          const dayName = document.createElement('div');
          dayName.className = 'day-name';
          dayName.textContent = day; // Názov dňa priamo z poľa
          
          dayHeader.appendChild(dayName);
          daySection.appendChild(dayHeader);
          
          const activitiesList = document.createElement('div');
          activitiesList.className = 'activities-list';
          
          console.log(`Vytváranie aktivít pre deň ${day}, počet: ${dayActivities.length}`);
          
          // Pridaj všetky aktivity pre daný deň, aj tie plne obsadené
          dayActivities.forEach(activity => {
            // Prevod na číslo pre správne porovnanie
            const availableSpots = parseInt(activity.available_spots, 10) || 0;
            
            const radioContainer = document.createElement('div');
            radioContainer.className = 'activity-option';
            
            // Označ nedostupné aktivity vizuálne
            if (availableSpots <= 0) {
              radioContainer.classList.add('disabled');
            } else if (availableSpots <= 3) {
              radioContainer.setAttribute('data-available', 'low');
            }
            
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = `activity-${day}`;
            radio.id = `activity-${activity.id}`;
            radio.value = activity.id;
            radio.setAttribute('data-activity-id', activity.id);
            
            // Deaktivuj nedostupné aktivity aby sa nedali vybrať
            if (availableSpots <= 0) {
              radio.disabled = true;
            }
            
            const label = document.createElement('label');
            label.htmlFor = `activity-${activity.id}`;
            
            const customRadio = document.createElement('span');
            customRadio.className = 'custom-radio';
            label.appendChild(customRadio);
            
            const activityName = document.createElement('span');
            activityName.className = 'activity-name';
            activityName.textContent = activity.nazov;
            label.appendChild(activityName);
            
            const capacitySpan = document.createElement('span');
            capacitySpan.className = 'activity-capacity';
            
            // Zobraz správu o počte miest alebo informáciu, že je nedostupná
            if (availableSpots <= 0) {
              capacitySpan.textContent = '(nedostupné)';
              capacitySpan.classList.add('fully-booked');
            } else {
              capacitySpan.textContent = `(${availableSpots} miest)`;
            }
            
            label.appendChild(capacitySpan);
            radioContainer.appendChild(radio);
            radioContainer.appendChild(label);
            activitiesList.appendChild(radioContainer);
            
            // Pridaj event listener len pre dostupné aktivity
            if (availableSpots > 0) {
              radioContainer.addEventListener('click', (e) => {
                if (e.target !== radio) {
                  radio.checked = true;
                  handleActivitySelection();
                }
              });
            }
            // Pre nedostupné aktivity žiadny listener nepridávame
          });
          
          daySection.appendChild(activitiesList);
          activitiesContainer.appendChild(daySection);
        }
        
        // Pridaj event listener pre všetky radio tlačidlá
        const activityRadios = document.querySelectorAll('input[type="radio"][name^="activity-"]');
        activityRadios.forEach(radio => {
          radio.addEventListener('change', handleActivitySelection);
        });
      }
    } catch (error) {
      console.error('Failed to load activities:', error);
      showError('Nepodarilo sa načítať zoznam aktivít. Skúste obnoviť stránku.');
    }
  }

  /**
   * Handle mladez (youth group) selection change
   */
  function handleMladezChange() {
    if (mladezSelect.value === 'iny') {
      vlastnyMladezContainer.style.display = 'block';
    } else {
      vlastnyMladezContainer.style.display = 'none';
    }
  }

  /**
   * Handle gender selection change
   */
  async function handleGenderChange() {
    // Don't load accommodation if the user is a guest
    if (jeHostCheckbox && jeHostCheckbox.checked) {
      return;
    }

    const selectedGender = document.querySelector('input[name="pohlavie"]:checked').value;

    try {
      // Build the URL with parameters
      const accommodationsUrl = `${API_BASE_URL}accommodations&gender=${selectedGender}&type=${regType}`;

      // Get accommodations based on gender and registration type
      const data = await fetchWithErrorHandling(accommodationsUrl);
      const accommodations = data.data || [];

      if (ubytovanieSelect) {
        // Clear existing options
        ubytovanieSelect.innerHTML = '';

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Vyberte ubytovanie...';
        ubytovanieSelect.appendChild(defaultOption);

        // Add options from the database
        if (accommodations.length > 0) {
          accommodations.forEach(accommodation => {
            const option = document.createElement('option');
            option.value = accommodation.id;
            option.textContent = `${accommodation.nazov} (${accommodation.available_spots} voľných miest)`;
            ubytovanieSelect.appendChild(option);
          });
        } else {
          // No accommodations available
          const noOption = document.createElement('option');
          noOption.value = '';
          noOption.textContent = 'Žiadne dostupné ubytovanie';
          ubytovanieSelect.appendChild(noOption);
          console.warn('No accommodations found for', selectedGender, regType);
        }
      }
    } catch (error) {
      console.error('Failed to load accommodations:', error);
      showError('Nepodarilo sa načítať ubytovanie. Skúste obnoviť stránku.');

      // Add fallback option
      if (ubytovanieSelect) {
        ubytovanieSelect.innerHTML = '';
        const errorOption = document.createElement('option');
        errorOption.value = '';
        errorOption.textContent = 'Chyba pri načítaní - skúste znova';
        ubytovanieSelect.appendChild(errorOption);
      }
    }
  }

  /**
   * Handle activity selection
   */
  function handleActivitySelection() {
    // Collect all selected activities
    const selectedActivities = [];
    const activityRadios = document.querySelectorAll('input[type="radio"][name^="activity-"]:checked');

    activityRadios.forEach(radio => {
      const activityId = radio.getAttribute('data-activity-id');
      if (activityId) {
        selectedActivities.push(activityId);
      }
    });

    // Update hidden input for form submission
    const hiddenActivitiesContainer = document.getElementById('hidden-activities');
    if (hiddenActivitiesContainer) {
      hiddenActivitiesContainer.innerHTML = '';

      selectedActivities.forEach(activityId => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'aktivity[]';
        hiddenInput.value = activityId;
        hiddenActivitiesContainer.appendChild(hiddenInput);
      });
    }
  }

  /**
   * Handle form submission
   * @param {Event} e Form submit event
   */
  async function handleFormSubmit(e) {
    e.preventDefault();

    // Clear previous errors
    clearErrors();

    // Collect activity selections before submission
    handleActivitySelection();

    // Collect allergy selections
    collectAllergies();

    // Log form data for debugging
    console.log("Form submission type:", regType);

    // Funkcia pre pridanie alebo aktualizáciu skrytého poľa
    function addOrUpdateHiddenField(name, value) {
      // Skontroluj, či už pole existuje
      let existingField = form.querySelector(`input[type="hidden"][name="${name}"]`);

      if (existingField) {
        // Aktualizuj hodnotu existujúceho poľa
        existingField.value = value;
        console.log(`Updated existing hidden field ${name} with value ${value}`);
      } else {
        // Vytvor nové pole
        let newField = document.createElement('input');
        newField.type = 'hidden';
        newField.name = name;
        newField.value = value;
        form.appendChild(newField);
        console.log(`Added new hidden field ${name} with value ${value}`);
      }
    }

    // Pre-validate based on registration type - Enhanced handling for hosts
    if (regType === 'host') {
      console.log("Processing host registration - ensuring all required fields have default values");

      // Zabezpeč, že existujú všetky potrebné skryté polia pre hostí
      addOrUpdateHiddenField('ubytovanie_id', '6');  // Host ubytovanie (ID 6)
      addOrUpdateHiddenField('mladez_id', 'iny');    // 'Iné' mládež
      addOrUpdateHiddenField('vlastny_mladez', 'Host'); // Vlastná mládež nastavená na "Host"

      // Zabezpečíme, že poznámka má správny text a je vyžadovaná
      setTimeout(() => {
        const poznamkaLabel = document.getElementById('poznamka-label');
        if (poznamkaLabel) {
          console.log("Updating note label text for host during form submission");
          poznamkaLabel.innerHTML = 'Sem pridajte potrebné informácie pre organizátorov, ako napríklad: ako dlho budete na kempe, či potrebujete nocľah, či máte nejaké potravinové alergie, alebo iné dôležité informácie.';
        }

        // Ak poznamka nie je required, urobíme ho required - pre hostí je dôležité
        const poznamkaField = document.getElementById('poznamka');
        if (poznamkaField && !poznamkaField.hasAttribute('required')) {
          poznamkaField.setAttribute('required', 'required');
          poznamkaField.rows = 5;
          poznamkaField.placeholder = 'Poznámka pre organizátorov';
          console.log("Made note field required for host");
        }
      }, 100);
    }

    // Validate form
    if (!validateForm()) {
      return;
    }

    // Disable submit button
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Registrujem...';
    }

    try {
      // Submit form data
      const formData = new FormData(form);

      // Log form data for debugging
      console.log("Submitting form data:");
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      // Add token for special registration if provided
      if (token && (regType === 'veduci' || regType === 'host')) {
        formData.append('token', token);
        console.log("Added token to form data:", token);
      }

      // Build the registration URL
      const registrationUrl = `${API_BASE_URL}register`;
      console.log("Registration URL:", registrationUrl);

      // Use our enhanced fetch function
      const result = await fetchWithErrorHandling(registrationUrl, {
        method: 'POST',
        body: formData
      });

      console.log("Registration result:", result);

      // Check for success flag in result
      if (result && result.success) {
        // Redirect to confirmation page directly without showing success message
        window.location.href = 'confirmation.html';
      } else {
        // Handle formatted error or malformed response
        let errorMessage = 'Nastala chyba pri registrácii. Skúste to znova.';

        if (result && result.error) {
          errorMessage = result.error;
        } else if (result && result.errors && Array.isArray(result.errors)) {
          errorMessage = result.errors.join(', ');
        } else if (result && result.rawResponse) {
          errorMessage = `Server error: ${result.rawResponse}`;
        }

        showError(errorMessage);
        console.error('Registration failed:', result);

        // Re-enable submit button
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Registrovať';
        }
      }
    } catch (error) {
      console.error('Registration error:', error);
      showError('Nastala chyba pri komunikácii so serverom. Skúste to znova neskôr.');

      // Re-enable submit button
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Registrovať';
      }
    }
  }

  /**
   * Collect all selected allergies for form submission
   */
  function collectAllergies() {
    // Get all checked allergy checkboxes
    const allergieCheckboxes = document.querySelectorAll('input[name="alergie"]:checked');

    // Remove existing hidden fields
    document.querySelectorAll('input[type="hidden"][name="alergie[]"]').forEach(input => {
      input.remove();
    });

    // Create hidden fields for each selected allergy
    allergieCheckboxes.forEach(checkbox => {
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'alergie[]';
      hiddenInput.value = checkbox.value;
      form.appendChild(hiddenInput);
    });
  }

  /**
   * Validate form before submission
   * @returns {boolean} Whether the form is valid
   */
  function validateForm() {
    let isValid = true;

    const requiredFields = [
      { id: 'meno', message: 'Zadajte vaše meno.' },
      { id: 'priezvisko', message: 'Zadajte vaše priezvisko.' },
      { id: 'email', message: 'Zadajte váš email.' },
      { id: 'datum_narodenia', message: 'Zadajte váš dátum narodenia.' }
    ];

    // Add mladez to required fields if participant or leader
    if (regType === 'ucastnik' || regType === 'veduci') {
      requiredFields.push({ id: 'mladez', message: 'Vyberte vaše spoločenstvo.' });
    }

    // Add ubytovanie to required fields only if not a host
    if (regType !== 'host' && !document.querySelector('input[type="hidden"][name="ubytovanie_id"]')) {
      requiredFields.push({ id: 'ubytovanie', message: 'Vyberte ubytovanie.' });
    }

    requiredFields.forEach(field => {
      const inputElement = document.getElementById(field.id);
      if (!inputElement || !inputElement.value) {
        showFieldError(field.id, field.message);
        isValid = false;
      }
    });

    // Validate gender
    const genderSelected = document.querySelector('input[name="pohlavie"]:checked');
    if (!genderSelected) {
      showError('Vyberte pohlavie.');
      isValid = false;
    }

    // Validate GDPR consent
    const gdprCheckbox = document.getElementById('gdpr');
    if (!gdprCheckbox || !gdprCheckbox.checked) {
      showFieldError('gdpr', 'Musíte súhlasiť so spracovaním osobných údajov.');
      isValid = false;
    }

    // Additional validation for special fields - for both participants and leaders
    if (regType !== 'host' && mladezSelect) {
      if (mladezSelect.value === '') {
        showFieldError('mladez', 'Vyberte vaše spoločenstvo.');
        isValid = false;
      } else if (mladezSelect.value === 'iny' && !document.getElementById('vlastny-mladez').value) {
        showFieldError('vlastny-mladez', 'Zadajte názov vášho spoločenstva.');
        isValid = false;
      }
    }

    // Validate birth date (must be at least 14 years old within this calendar year)
    const birthDateField = document.getElementById('datum_narodenia');
    if (birthDateField && birthDateField.value) {
      const birthDate = new Date(birthDateField.value);
      const today = new Date();

      // Check if they will be 14 by the end of this year
      const currentYear = today.getFullYear();
      const birthYear = birthDate.getFullYear();
      const age = currentYear - birthYear;

      if (age < 14) {
        showFieldError('datum_narodenia', 'Musíte mať aspoň 14 rokov v tomto kalendárnom roku.');
        isValid = false;
      }
    }

    // For hosts, check if they mentioned accommodation in the note - only if jeHostCheckbox exists
    if (regType === 'host') {
      const poznamkaField = document.getElementById('poznamka');

      if (poznamkaField && poznamkaField.value) {
        const poznamkaText = poznamkaField.value.toLowerCase();

        // Skip this validation temporarily to allow registration to proceed
      }
    }

    return isValid;
  }

  /**
   * Show error message for a specific field
   * @param {string} fieldId Field ID
   * @param {string} message Error message
   */
  function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (field) {
      field.classList.add('error');

      // Add error message after the field
      const errorElement = document.createElement('div');
      errorElement.className = 'field-error';
      errorElement.textContent = message;

      const parent = field.parentElement;
      if (parent) {
        parent.appendChild(errorElement);
      }
    }
  }

  /**
   * Show general error message
   * @param {string} message Error message
   */
  function showError(message) {
    if (errorContainer) {
      errorContainer.textContent = message;
      errorContainer.style.display = 'block';
    }
  }

  /**
   * Clear all error messages
   */
  function clearErrors() {
    // Clear general error container
    if (errorContainer) {
      errorContainer.textContent = '';
      errorContainer.style.display = 'none';
    }

    // Remove field-specific error classes
    const errorFields = document.querySelectorAll('.error');
    errorFields.forEach(field => {
      field.classList.remove('error');
    });

    // Remove field-specific error messages
    const errorMessages = document.querySelectorAll('.field-error');
    errorMessages.forEach(message => {
      message.remove();
    });
  }
});
