// settings.js
// TutorMind - User Settings Manager
class SettingsManager {
    constructor() {
        this.modal = null;
        this.currentTab = 'account';
        this.initialSettings = {};
        this.dirty = false; // To track unsaved changes
        this.isPopulating = false; // Flag to prevent input events during form population

        // Debounce save function for toggle switches
        this.debouncedSave = this.debounce(this.saveToggle.bind(this), 500);

        // Bind methods
        this.open = this.open.bind(this);
        this.close = this.close.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.saveTextInputs = this.saveTextInputs.bind(this);
    }

    /**
     * Initializes the settings manager by creating the modal HTML and attaching events.
     */
    init() {
        this.createModalHtml();
        this.modal = document.getElementById('settings-modal');
        this.attachEventListeners();
    }

    /**
     * Creates and injects the settings modal HTML into the body.
     */
    createModalHtml() {
        if (document.getElementById('settings-modal')) return;

        const modalHtml = /*html*/`
            <div id="settings-modal" class="settings-modal hidden">
                <div class="settings-overlay" data-action="close"></div>
                <div class="settings-container" role="dialog" aria-modal="true" aria-labelledby="settings-title">
                    <header class="settings-header">
                        <h2 id="settings-title"><i class="fas fa-cog"></i> Settings</h2>
                        <button class="close-settings" data-action="close" aria-label="Close settings"><i class="fas fa-times"></i></button>
                    </header>
                    
                    <div class="settings-body">
                        <nav class="settings-tabs" aria-label="Settings categories">
                            <button class="tab-btn active" data-tab="account" role="tab" aria-selected="true"><i class="fas fa-user-circle"></i> Account</button>
                            <button class="tab-btn" data-tab="security" role="tab" aria-selected="false"><i class="fas fa-shield-alt"></i> Security</button>
                            <button class="tab-btn" data-tab="notifications" role="tab" aria-selected="false"><i class="fas fa-bell"></i> Notifications</button>
                            <button class="tab-btn" data-tab="appearance" role="tab" aria-selected="false"><i class="fas fa-palette"></i> Appearance</button>
                            <button class="tab-btn" data-tab="privacy" role="tab" aria-selected="false"><i class="fas fa-user-secret"></i> Privacy & Data</button>
                        </nav>
                        
                        <main class="settings-content">
                            <!-- Panels will be injected here -->
                        </main>
                    </div>
                    
                    <footer class="settings-footer">
                        <button class="btn-cancel" data-action="close">Cancel</button>
                        <button class="btn-save" id="settings-save-btn">
                            <span class="btn-text">Save Changes</span>
                            <span class="spinner"></span>
                        </button>
                    </footer>
                </div>
            </div>
            <div id="settings-confirm-dialog" class="confirm-dialog hidden">
                <div class="confirm-dialog-box">
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3 id="confirm-title">Are you sure?</h3>
                    <p id="confirm-message">This action cannot be undone.</p>
                    <div class="confirm-dialog-actions">
                        <button class="btn-settings btn-cancel" id="confirm-cancel-btn">Cancel</button>
                        <button class="btn-settings btn-danger" id="confirm-proceed-btn">Proceed</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.injectTabPanels();
    }

    /**
     * Injects the HTML for each settings tab panel into the content area.
     */
    injectTabPanels() {
        // Find the modal that was just added to the DOM
        const modalElement = document.getElementById('settings-modal');
        modalElement.querySelector('.settings-content').innerHTML = /*html*/`
            <!-- ACCOUNT PANEL -->
            <div id="tab-account" class="tab-panel active">
                <h3>Profile Information</h3>
                <form id="account-form">
                    <div class="form-group">
                        <label for="settings-full-name">Full Name</label>
                        <input type="text" id="settings-full-name" class="form-control" data-setting="full_name" placeholder="e.g., Jane Doe">
                    </div>
                    <div class="form-group">
                        <label for="settings-username">Username</label>
                        <input type="text" id="settings-username" class="form-control" data-setting="username" placeholder="e.g., janedoe">
                    </div>
                    <div class="form-group">
                        <label for="settings-email">Email Address</label>
                        <input type="email" id="settings-email" class="form-control" data-setting="email" placeholder="e.g., jane.doe@example.com">
                        <p class="form-error-message" id="email-error">Please enter a valid email.</p>
                    </div>
                    <div class="form-group">
                        <label for="settings-created-at">Account Created</label>
                        <input type="text" id="settings-created-at" class="form-control" disabled>
                    </div>
                </form>
                <div class="form-group">
                    <h3>Learning Preferences</h3>
                    <p>Customize the AI's teaching style to match your needs.</p>
                    <div class="form-group">
                        <label for="settings-learning-level">Default Learning Level</label>
                        <select id="settings-learning-level" class="form-select" data-setting="learning_level">
                            <option value="Remember">Remember</option>
                            <option value="Understand">Understand</option>
                            <option value="Apply">Apply</option>
                            <option value="Analyze">Analyze</option>
                            <option value="Evaluate">Evaluate</option>
                            <option value="Create">Create</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="settings-response-style">Preferred Response Style</label>
                        <select id="settings-response-style" class="form-select" data-setting="response_style">
                            <option value="concise">Concise</option>
                            <option value="detailed">Detailed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECURITY PANEL -->
            <div id="tab-security" class="tab-panel">
                <h3>Change Password</h3>
                <p>For your security, we recommend using a long, unique password.</p>
                <form id="password-form">
                    <div class="form-group">
                        <label for="settings-current-password">Current Password</label>
                        <div class="input-group">
                            <input type="password" id="settings-current-password" class="form-control">
                            <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="settings-new-password">New Password</label>
                        <div class="input-group">
                            <input type="password" id="settings-new-password" class="form-control">
                             <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                        </div>
                        <p class="password-strength-text"></p>
                        <p class="form-error-message" id="new-password-error">Password must be at least 8 characters.</p>
                    </div>
                    <div class="form-group">
                        <label for="settings-confirm-password">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" id="settings-confirm-password" class="form-control">
                             <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <p class="form-error-message" id="confirm-password-error">Passwords do not match.</p>
                    </div>
                    <button class="btn-save" id="change-password-btn">Update Password</button>
                </form>
            </div>

            <!-- NOTIFICATIONS PANEL -->
            <div id="tab-notifications" class="tab-panel">
                <h3>Email Notifications</h3>
                <p>Choose which emails you want to receive from TutorMind.</p>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Email Notifications</h4>
                        <p>Receive important updates about your account.</p>
                    </div>
                    <label class="switch"><input type="checkbox" data-setting="email_notifications"><span class="slider"></span></label>
                </div>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Study Reminders</h4>
                        <p>Get occasional reminders to keep up with your learning.</p>
                    </div>
                    <label class="switch"><input type="checkbox" data-setting="study_reminders"><span class="slider"></span></label>
                </div>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>New Feature Announcements</h4>
                        <p>Be the first to know about new tools and features.</p>
                    </div>
                    <label class="switch"><input type="checkbox" data-setting="feature_announcements"><span class="slider"></span></label>
                </div>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Weekly Summary</h4>
                        <p>Receive a weekly digest of your learning activity.</p>
                    </div>
                    <label class="switch"><input type="checkbox" data-setting="weekly_summary"><span class="slider"></span></label>
                </div>
            </div>

            <!-- APPEARANCE PANEL -->
            <div id="tab-appearance" class="tab-panel">
                <h3>Theme & Layout</h3>
                <p>Customize how TutorMind looks and feels.</p>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Dark Mode</h4>
                        <p>Reduces eye strain in low-light environments.</p>
                    </div>
                    <label class="switch"><input type="checkbox" id="settings-dark-mode" data-setting="dark_mode"><span class="slider"></span></label>
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <label>Font Size</label>
                    <div class="option-group" data-setting="font_size">
                        <button class="option-btn" data-value="small">Small</button>
                        <button class="option-btn active" data-value="medium">Medium</button>
                        <button class="option-btn" data-value="large">Large</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Chat Density</label>
                    <div class="option-group" data-setting="chat_density">
                        <button class="option-btn" data-value="compact">Compact</button>
                        <button class="option-btn active" data-value="comfortable">Comfortable</button>
                    </div>
                </div>
                
                <h3 style="margin-top: 24px;">Accessibility</h3>
                <p>Improve readability for visual comfort.</p>
                <div class="form-group legibility-slider-group">
                    <label for="settings-legibility">
                        <i class="fas fa-eye"></i> Text Legibility
                        <span class="legibility-value" id="legibility-value">100%</span>
                    </label>
                    <input type="range" id="settings-legibility" class="legibility-slider" 
                           min="90" max="150" value="100" step="5" data-setting="legibility">
                    <div class="legibility-hint">
                        <span>Smaller</span>
                        <span>Default</span>
                        <span>Larger</span>
                    </div>
                    <p class="legibility-description">Adjusts font size and line spacing together for easier reading.</p>
                </div>
            </div>

            <!-- PRIVACY PANEL -->
            <div id="tab-privacy" class="tab-panel">
                <h3>Privacy Settings</h3>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Help improve TutorMind</h4>
                        <p>Allow us to use anonymized data to improve our AI.</p>
                    </div>
                    <label class="switch"><input type="checkbox" data-setting="data_sharing"><span class="slider"></span></label>
                </div>

                <div class="danger-zone">
                    <h4>Data Management</h4>
                    <div class="action-item">
                        <div class="action-item-label">
                            <p>Permanently delete all your conversation history.</p>
                        </div>
                        <button class="btn-danger" id="clear-history-btn">Clear All History</button>
                    </div>
                    <div class="action-item">
                        <div class="action-item-label">
                            <p>Download an archive of your data.</p>
                        </div>
                        <button class="btn-cancel" id="download-data-btn">Request Data Archive</button>
                    </div>
                </div>

                <div class="danger-zone">
                    <h4>Account Deletion</h4>
                     <div class="action-item">
                        <div class="action-item-label">
                           <p>Permanently delete your account and all associated data.</p>
                        </div>
                        <button class="btn-danger" id="delete-account-btn">Delete My Account</button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Attaches all necessary event listeners for the modal.
     */
    attachEventListeners() {
        // Main modal actions (close button and overlay)
        this.modal.addEventListener('click', (e) => {
            const closeElement = e.target.closest('[data-action="close"]');
            if (closeElement) {
                this.close();
            }
        });

        // Tab switching
        this.modal.querySelector('.settings-tabs').addEventListener('click', (e) => {
            const tabButton = e.target.closest('.tab-btn');
            if (tabButton) {
                this.switchTab(tabButton.dataset.tab);
            }
        });

        // Save button for text inputs
        this.modal.querySelector('#settings-save-btn').addEventListener('click', this.saveTextInputs);

        // Input change tracking
        this.modal.querySelector('.settings-content').addEventListener('input', (e) => {
            // Don't set dirty if we're programmatically populating the form
            if (this.isPopulating) return;
            
            const target = e.target;
            if (target.classList.contains('form-control') || target.classList.contains('form-select')) {
                if (target.closest('#tab-account') || target.closest('#tab-appearance')) {
                    this.dirty = true;
                    this.modal.querySelector('#settings-save-btn').classList.remove('loading');
                }
            }
        });

        // Toggle switch auto-saving
        this.modal.querySelectorAll('.switch input[type="checkbox"]').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const setting = e.target.dataset.setting;
                const value = e.target.checked;
                console.log('Toggle changed:', setting, '=', value); // Debug logging
                this.debouncedSave({ [setting]: value });

                // Special case for dark mode to apply immediately
                if (setting === 'dark_mode') {
                    console.log('Dark mode toggle - applying immediately'); // Debug logging
                    document.body.classList.toggle('dark-mode', value);
                    // Sync with the main toggle in the user menu
                    const mainToggle = document.getElementById('darkModeToggle');
                    if (mainToggle) mainToggle.checked = value;
                    // Sync with localStorage
                    localStorage.setItem('darkMode', value ? 'enabled' : 'disabled');
                }
            });
        });
        
        // Appearance option buttons (font size, density)
        this.modal.querySelectorAll('.option-group').forEach(group => {
            group.addEventListener('click', e => {
                if (e.target.classList.contains('option-btn')) {
                    const setting = group.dataset.setting;
                    const value = e.target.dataset.value;
                    this.updateOptionButtons(group, value);
                    this.dirty = true;
                    this.modal.querySelector('#settings-save-btn').classList.remove('loading');
                }
            });
        });
        
        // Legibility slider (real-time preview + save)
        const legibilitySlider = this.modal.querySelector('#settings-legibility');
        if (legibilitySlider) {
            legibilitySlider.addEventListener('input', (e) => {
                const value = e.target.value;
                // Update display value
                this.modal.querySelector('#legibility-value').textContent = value + '%';
                // Apply immediately for live preview
                this.applyLegibility(value);
                this.dirty = true;
            });
            
            // Save on change (when user releases slider)
            legibilitySlider.addEventListener('change', (e) => {
                const value = parseInt(e.target.value);
                this.debouncedSave({ legibility: value });
                localStorage.setItem('legibility', value);
            });
        }

        // --- Security Tab Listeners ---
        this.attachSecurityListeners();
        
        // --- Privacy Tab Listeners ---
        this.attachPrivacyListeners();
    }
    
    /**
     * Attaches event listeners specific to the Security tab.
     */
    attachSecurityListeners() {
        const securityTab = this.modal.querySelector('#tab-security');
        
        // Password visibility toggles
        securityTab.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.previousElementSibling;
                const icon = btn.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });

        // Password strength indicator
        const newPasswordInput = securityTab.querySelector('#settings-new-password');
        newPasswordInput.addEventListener('input', () => this.updatePasswordStrength(newPasswordInput.value));

        // Change password button
        securityTab.querySelector('#change-password-btn').addEventListener('click', this.changePassword.bind(this));
    }
    
    /**
     * Attaches event listeners specific to the Privacy tab.
     */
    attachPrivacyListeners() {
        // Delete account button
        this.modal.querySelector('#delete-account-btn').addEventListener('click', () => {
            this.showConfirmDialog({
                title: 'Delete Account?',
                message: 'This is permanent. All your data, including chat history, will be erased. Please enter your password to confirm.',
                action: this.deleteAccount.bind(this),
                needsPassword: true
            });
        });

        // Clear history button
        this.modal.querySelector('#clear-history-btn').addEventListener('click', () => {
            this.showConfirmDialog({
                title: 'Clear All Chat History?',
                message: 'This will permanently delete all your conversations. This action cannot be undone.',
                action: this.clearChatHistory.bind(this)
            });
        });
    }

    /**
     * Opens the settings modal.
     */
    open() {
        document.addEventListener('keydown', this.handleKeyDown);
        this.modal.classList.remove('hidden'); // This removes display: none !important;
        this.modal.querySelector('.tab-btn').focus();
        this.loadSettings();
    }

    /**
     * Closes the settings modal.
     */
    close() {
        if (this.dirty && !confirm('You have unsaved changes. Are you sure you want to close?')) {
            return;
        }
        document.removeEventListener('keydown', this.handleKeyDown);
        this.modal.classList.add('hidden'); // This adds display: none !important;
        this.resetFormState();
    }

    /**
     * Handles keyboard shortcuts like ESC and Ctrl+S.
     * @param {KeyboardEvent} e The keyboard event.
     */
    handleKeyDown(e) {
        const confirmDialog = document.getElementById('settings-confirm-dialog');
        if (e.key === 'Escape' && (!confirmDialog || confirmDialog.classList.contains('hidden'))) {
            this.close();
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (!this.modal.querySelector('#settings-save-btn').disabled) {
                this.saveTextInputs();
            }
        }
    }

    /**
     * Switches the visible tab.
     * @param {string} tabName The name of the tab to switch to.
     */
    switchTab(tabName) {
        this.currentTab = tabName;

        // Update tab buttons
        this.modal.querySelectorAll('.tab-btn').forEach(btn => {
            const isActive = btn.dataset.tab === tabName;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive);
        });

        // Update tab panels
        this.modal.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.toggle('active', panel.id === `tab-${tabName}`);
        });

        // Show/hide the main save button based on the tab
        const saveBtn = this.modal.querySelector('#settings-save-btn');
        if (tabName === 'account' || tabName === 'appearance' || tabName === 'notifications') {
            saveBtn.style.display = 'inline-block';
        } else {
            saveBtn.style.display = 'none';
        }
    }

    /**
     * Fetches the current user settings from the API and populates the form.
     */
    async loadSettings() {
        this.showLoadingState(true);
        try {
            const response = await fetch('api/user_settings.php');
            if (!response.ok) throw new Error('Failed to load settings.');

            const data = await response.json();
            if (data.success) {
                console.log('Settings loaded:', data.settings); // Debug logging
                this.initialSettings = data.settings;
                this.populateForm(data.settings);
                this.applyGlobalSettings(data.settings);
            } else {
                throw new Error(data.error || 'Unknown error loading settings.');
            }
        } catch (error) {
            console.error('Settings load error:', error); // Debug logging
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            this.showLoadingState(false);
        }
    }

    /**
     * Applies settings to the global application UI (outside the modal).
     * @param {object} settings The settings object.
     */
    applyGlobalSettings(settings) {
        console.log('Applying global settings:', settings); // Debug logging
        
        // Apply Learning Level to the main chat input
        const mainLearningLevel = document.getElementById('learningLevel');
        if (mainLearningLevel && settings.learning_level) {
            mainLearningLevel.value = settings.learning_level;
        }

        // Apply Font Size
        if (settings.font_size) {
            document.documentElement.style.fontSize = settings.font_size === 'small' ? '14px' : (settings.font_size === 'large' ? '18px' : '16px');
        }

        // Apply Chat Density
        if (settings.chat_density) {
            document.body.classList.toggle('compact-mode', settings.chat_density === 'compact');
        }
        
        // Apply Dark Mode and sync with localStorage
        if (settings.dark_mode !== undefined) {
             const isDark = !!settings.dark_mode;
             const wasDark = document.body.classList.contains('dark-mode');
             console.log('Dark mode - Current:', wasDark, 'Setting to:', isDark, 'Raw value:', settings.dark_mode);
             document.body.classList.toggle('dark-mode', isDark);
             const mainToggle = document.getElementById('darkModeToggle');
             if (mainToggle) mainToggle.checked = isDark;
             // Update localStorage to match database setting
             localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
             console.log('Dark mode applied. Body has dark-mode class:', document.body.classList.contains('dark-mode'));
        }
        
        // Apply Legibility setting
        if (settings.legibility !== undefined) {
            this.applyLegibility(settings.legibility);
            localStorage.setItem('legibility', settings.legibility);
        }
    }
    
    /**
     * Applies legibility scaling (font size and line height).
     * @param {number} value The legibility percentage (90-150).
     */
    applyLegibility(value) {
        const scale = value / 100;
        // Set CSS custom properties for legibility scaling
        document.documentElement.style.setProperty('--legibility-scale', scale);
        document.documentElement.style.setProperty('--legibility-font-size', `${scale}rem`);
        document.documentElement.style.setProperty('--legibility-line-height', `${1.5 + (scale - 1) * 0.4}`);
        
        // Update slider value display if visible
        const valueDisplay = document.getElementById('legibility-value');
        if (valueDisplay) {
            valueDisplay.textContent = value + '%';
        }
        
        // Update slider position if visible
        const slider = document.getElementById('settings-legibility');
        if (slider) {
            slider.value = value;
        }
    }

    /**
     * Populates the form fields with data from the API.
     * @param {object} settings The user settings object.
     */
    populateForm(settings) {
        // Set flag to prevent input events from marking form as dirty
        this.isPopulating = true;
        
        // Text inputs, selects, and checkboxes
        this.modal.querySelectorAll('[data-setting]').forEach(el => {
            const key = el.dataset.setting;
            if (settings.hasOwnProperty(key)) {
                if (el.matches('input[type="checkbox"]')) {
                    el.checked = !!settings[key];
                } else if (el.classList.contains('option-group')) {
                    this.updateOptionButtons(el, settings[key]);
                } else {
                    el.value = settings[key];
                }
            }
        });


        // Read-only fields
        const createdAt = this.modal.querySelector('#settings-created-at');
        if (createdAt && settings.created_at) {
            createdAt.value = new Date(settings.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            });
        }
        
        // Sync dark mode toggle with main UI toggle
        const mainToggle = document.getElementById('darkModeToggle');
        this.resetFormState();
        
        // Clear the flag after a short delay to ensure all events have settled
        setTimeout(() => {
            this.isPopulating = false;
        }, 100);
    }

    /**
     * Saves settings that are changed via toggle switches.
     * @param {object} settings An object containing the setting key and value.
     */
    async saveToggle(settings) {
        console.log('saveToggle called with:', settings); // Debug logging
        try {
            const response = await fetch('api/user_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
            const result = await response.json();
            console.log('saveToggle API response:', result); // Debug logging
            if (result.success) {
                this.showToast('Setting saved!', 'success');
                this.applyGlobalSettings(settings);
                // Update initialSettings to keep in sync
                Object.assign(this.initialSettings, settings);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('saveToggle error:', error); // Debug logging
            this.showToast('Error: ' + error.message, 'error');
            // Revert the toggle if save fails
            const key = Object.keys(settings)[0];
            const toggle = this.modal.querySelector(`[data-setting="${key}"]`);
            if(toggle) toggle.checked = !toggle.checked;
        }
    }

    /**
     * Saves settings from text inputs and select dropdowns.
     */
    async saveTextInputs() {
        const saveBtn = this.modal.querySelector('#settings-save-btn');
        saveBtn.classList.add('loading');

        const payload = {};
        let changed = false;
        
        // Get values from text inputs and selects
        this.modal.querySelectorAll('#tab-account [data-setting], #tab-appearance select[data-setting]').forEach(el => {
            const key = el.dataset.setting;
            const currentValue = el.type === 'checkbox' ? el.checked : el.value;
            if (this.initialSettings[key] !== undefined && currentValue !== this.initialSettings[key]) {
                payload[key] = currentValue;
                changed = true;
            }
        });

        // Get values from option button groups
        this.modal.querySelectorAll('#tab-appearance .option-group').forEach(group => {
            const key = group.dataset.setting;
            const currentValue = group.querySelector('.option-btn.active').dataset.value;
            if (currentValue !== this.initialSettings[key]) {
                payload[key] = currentValue;
                changed = true;
            }
        });
        
        // Get legibility slider value
        const legibilitySlider = this.modal.querySelector('#settings-legibility');
        if (legibilitySlider) {
            const legibilityValue = parseInt(legibilitySlider.value);
            if (legibilityValue !== this.initialSettings.legibility) {
                payload.legibility = legibilityValue;
                changed = true;
            }
        }

        if (!changed) {
            this.showToast('No changes to save.', 'info');
            saveBtn.classList.remove('loading');
            return;
        }

        try {
            const response = await fetch('api/user_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                this.showToast('Settings saved successfully!', 'success');
                this.updateMainUI(payload);
                
                // Update initialSettings with saved values to prevent dirty flag
                Object.assign(this.initialSettings, payload);
                
                // Apply the saved settings globally
                this.applyGlobalSettings(payload);
                
                // Mark as clean
                this.dirty = false;
            } else {
                throw new Error(result.error || 'Failed to save.');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            this.resetFormState();
        }
    }
    
    /**
     * Updates the main UI (sidebar) with new user details after a save.
     * @param {object} updatedSettings An object with the changed settings.
     */
    updateMainUI(updatedSettings) {
        const { full_name, email, username } = updatedSettings;

        // Determine the display name, falling back to username if full_name is empty
        const displayName = full_name || this.initialSettings.username;
        const newInitial = displayName ? displayName.charAt(0).toUpperCase() : '?';

        if (full_name !== undefined || username !== undefined) {
            // Update all name displays
            document.querySelectorAll('.user-details h4').forEach(el => {
                el.textContent = displayName;
            });
            // Update all avatar initials
            document.querySelectorAll('.user-avatar').forEach(el => {
                el.textContent = newInitial;
            });
        }

        if (email !== undefined) {
            // Update all email displays
            document.querySelectorAll('.user-details p').forEach(el => {
                el.textContent = email;
            });
        }
    }

    /**
     * Updates the active state of option buttons (like font size).
     * @param {HTMLElement} group The container for the option buttons.
     * @param {string} value The value of the button to activate.
     */
    updateOptionButtons(group, value) {
        group.querySelectorAll('.option-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.value === value);
        });
    }

    /**
     * Handles the logic for changing a user's password.
     */
    async changePassword() {
        // Clear previous errors
        this.clearPasswordErrors();

        const currentPassword = this.modal.querySelector('#settings-current-password').value;
        const newPassword = this.modal.querySelector('#settings-new-password').value;
        const confirmPassword = this.modal.querySelector('#settings-confirm-password').value;

        // Frontend validation
        let isValid = true;
        if (newPassword.length < 8) {
            this.modal.querySelector('#settings-new-password').classList.add('is-invalid');
            this.modal.querySelector('#new-password-error').style.display = 'block';
            isValid = false;
        }
        if (newPassword !== confirmPassword) {
            this.modal.querySelector('#settings-confirm-password').classList.add('is-invalid');
            this.modal.querySelector('#confirm-password-error').style.display = 'block';
            isValid = false;
        }
        if (!currentPassword || !newPassword || !confirmPassword) {
            this.showToast('Please fill all password fields.', 'error');
            isValid = false;
        }
        if (!isValid) return;

        const changeBtn = this.modal.querySelector('#change-password-btn');
        changeBtn.classList.add('loading');
        changeBtn.querySelector('.btn-text').textContent = 'Updating...';

        try {
            // Fetch CSRF token before submitting
            const tokenResponse = await fetch('csrf.php?action=get_token');
            const tokenData = await tokenResponse.json();

            const formData = new FormData();
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('csrf_token', tokenData.token);

            const response = await fetch('auth_mysql.php?action=change_password', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok && result.success) {
                this.showToast('Password changed successfully!', 'success');
                this.clearPasswordFields();
            } else {
                throw new Error(result.error || 'An unknown error occurred.');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            changeBtn.classList.remove('loading');
            changeBtn.querySelector('.btn-text').textContent = 'Update Password';
        }
    }
    
    /**
     * Handles the logic for deleting a user's account.
     * @param {string} password The user's current password for confirmation.
     */
    async deleteAccount(password) {
        if (!password) {
            this.showToast('Password is required to delete your account.', 'error');
            return;
        }
        
        try {
            const response = await fetch('api/delete_account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: password })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showToast('Account deleted. You will be logged out.', 'success');
                // Redirect to login page after a short delay
                setTimeout(() => window.location.href = 'login', 2000);
            } else {
                throw new Error(result.error || 'Failed to delete account.');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        }
    }

    /**
     * Handles the logic for clearing user's chat history.
     */
    async clearChatHistory() {
        const toast = this.showToast('Clearing history... please wait.', 'info');
        try {
            const response = await fetch('api/clear_history.php', {
                method: 'POST'
            });

            const result = await response.json();

            if (response.ok && result.success) {
                this.showToast('Chat history cleared successfully!', 'success');
                // Refresh the chat history list in the main UI and start a new chat
                if (typeof loadChatHistory === 'function' && document.getElementById('newChatBtn')) {
                    loadChatHistory();
                    document.getElementById('newChatBtn').click();
                }
            } else {
                throw new Error(result.error || 'Failed to clear history.');
            }
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
        }
    }

    /**
     * Updates the password strength indicator based on password complexity.
     * @param {string} password The password to evaluate.
     */
    updatePasswordStrength(password) {
        const strengthText = this.modal.querySelector('.password-strength-text');
        const strengthBars = this.modal.querySelectorAll('.strength-bar');
        strengthBars.forEach(bar => bar.className = 'strength-bar');

        let score = 0;
        let text = '';
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        if (password.length > 0 && score <= 2) {
            text = 'Weak';
            strengthBars[0].classList.add('weak');
        } else if (score <= 4) {
            text = 'Medium';
            strengthBars[0].classList.add('medium');
            strengthBars[1].classList.add('medium');
        } else {
            text = 'Strong';
            strengthBars.forEach(bar => bar.classList.add('strong'));
        }
        strengthText.textContent = text;
    }

    /**
     * Shows a custom confirmation dialog for destructive actions.
     * @param {object} options Configuration for the dialog.
     */
    showConfirmDialog({ title, message, action, needsPassword = false }) {
        const dialog = document.getElementById('settings-confirm-dialog');
        dialog.classList.remove('hidden'); // Use the global .hidden class
        dialog.querySelector('#confirm-title').textContent = title;
        
        const messageContainer = dialog.querySelector('.confirm-dialog-box');
        let existingMessage = messageContainer.querySelector('#confirm-message');
        if (existingMessage) existingMessage.parentElement.innerHTML = `<p id="confirm-message">${message}</p>`;

        if (needsPassword) {
            const passwordHtml = `
                <div class="form-group" style="text-align: left; margin-top: 16px;">
                    <label for="confirm-password-input">Password</label>
                    <input type="password" id="confirm-password-input" class="form-control" placeholder="Enter your password">
                </div>
            `;
            dialog.querySelector('#confirm-message').insertAdjacentHTML('afterend', passwordHtml);
        }

        const cancelBtn = dialog.querySelector('#confirm-cancel-btn');
        const proceedBtn = dialog.querySelector('#confirm-proceed-btn');

        const cleanup = () => {
            dialog.classList.add('hidden'); // Use the global .hidden class
            // Important: remove event listeners to prevent multiple executions
            proceedBtn.removeEventListener('click', proceedHandler);
            cancelBtn.removeEventListener('click', cleanup);
        };

        const proceedHandler = () => {
            const passwordInput = dialog.querySelector('#confirm-password-input');
            const password = passwordInput ? passwordInput.value : null;
            if (typeof action === 'function') {
                action(password);
            }
            cleanup();
        };

        cancelBtn.addEventListener('click', cleanup, { once: true });
        proceedBtn.addEventListener('click', proceedHandler, { once: true });
    }

    // --- UTILITY & HELPER METHODS ---

    showToast(message, type = 'info') {
        const toast = document.getElementById('copy-toast'); // Reusing existing toast
        if (!toast) return;
        toast.textContent = message;
        toast.style.backgroundColor = type === 'error' ? '#ef4444' : (type === 'success' ? '#22c55e' : '#333');
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }

    showLoadingState(isLoading) {
        const container = this.modal.querySelector('.settings-container');
        if (isLoading) {
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner-overlay';
            spinner.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x"></i>';
            container.appendChild(spinner);
        } else {
            const spinner = container.querySelector('.loading-spinner-overlay');
            if (spinner) spinner.remove();
        }
    }

    resetFormState() {
        this.dirty = false;
        const saveBtn = this.modal.querySelector('#settings-save-btn');
        saveBtn.classList.remove('loading');
        saveBtn.querySelector('.btn-text').textContent = 'Save Changes';
        this.clearPasswordFields();
        this.clearPasswordErrors();
    }

    clearPasswordFields() {
        this.modal.querySelector('#settings-current-password').value = '';
        this.modal.querySelector('#settings-new-password').value = '';
        this.modal.querySelector('#settings-confirm-password').value = '';
        this.updatePasswordStrength('');
    }
    
    clearPasswordErrors() {
        this.modal.querySelector('#settings-new-password').classList.remove('is-invalid');
        this.modal.querySelector('#new-password-error').style.display = 'none';
        this.modal.querySelector('#settings-confirm-password').classList.remove('is-invalid');
        this.modal.querySelector('#confirm-password-error').style.display = 'none';
    }

    debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }
}
