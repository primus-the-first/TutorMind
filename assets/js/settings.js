// settings.js
// TutorMind - User Settings Manager
class SettingsManager {
    constructor() {
        this.modal = null;
        this.currentTab = 'account';
        this.initialSettings = {};
        this.dirty = false; // Unsaved edits to the Account text fields (the only fields that need "Save")
        this.isPopulating = false; // Flag to prevent input events during form population
        this.loaded = false; // initialSettings holds a server copy, so open() can render instantly
        this.inflight = null; // the settings GET in progress; callers share it instead of racing
        this.unsaved = {}; // changes made here that the server hasn't confirmed yet — a load must not undo them
        this.interests = []; // Personalization chips, mirrored from initialSettings.interests

        // Everything except the Account text fields saves as soon as it changes.
        // Changes are merged and sent together, so two quick edits to different
        // settings can't drop the first (a plain debounce kept only the last call).
        this.pendingSave = {};
        this.flushSave = this.debounce(() => {
            const payload = this.pendingSave;
            this.pendingSave = {};
            this.saveToggle(payload);
        }, 500);
        this.debouncedSave = (settings) => {
            Object.assign(this.pendingSave, settings);
            Object.assign(this.unsaved, settings);
            this.flushSave();
        };

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
                        <h2 id="settings-title">Settings</h2>
                        <button type="button" class="close-settings" data-action="close" aria-label="Close settings"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </header>

                    <div class="settings-body">
                        <nav class="settings-tabs" role="tablist" aria-label="Settings categories">
                            <button type="button" class="tab-btn active" data-tab="account" role="tab" aria-selected="true">Account</button>
                            <button type="button" class="tab-btn" data-tab="personalization" role="tab" aria-selected="false">Personalization</button>
                            <button type="button" class="tab-btn" data-tab="security" role="tab" aria-selected="false">Security</button>
                            <button type="button" class="tab-btn" data-tab="notifications" role="tab" aria-selected="false">Notifications</button>
                            <button type="button" class="tab-btn" data-tab="appearance" role="tab" aria-selected="false">Appearance</button>
                            <button type="button" class="tab-btn" data-tab="privacy" role="tab" aria-selected="false">Privacy and data</button>
                        </nav>
                        
                        <main class="settings-content">
                            <!-- Panels will be injected here -->
                        </main>
                    </div>
                    
                    <footer class="settings-footer">
                        <button type="button" class="btn-cancel" data-action="close">Close</button>
                        <button type="button" class="btn-save" id="settings-save-btn" disabled>
                            <span class="btn-text">Save changes</span>
                            <span class="spinner"></span>
                        </button>
                    </footer>
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
                <h3>Profile</h3>
                <p>Your name and how you sign in.</p>
                <form id="account-form" novalidate>
                    <div class="form-group">
                        <label for="settings-first-name">First name</label>
                        <input type="text" id="settings-first-name" class="form-control" data-setting="first_name" placeholder="e.g., Jane" autocomplete="given-name">
                    </div>
                    <div class="form-group">
                        <label for="settings-last-name">Last name</label>
                        <input type="text" id="settings-last-name" class="form-control" data-setting="last_name" placeholder="e.g., Doe" autocomplete="family-name">
                    </div>
                    <div class="form-group">
                        <label for="settings-username">Username</label>
                        <input type="text" id="settings-username" class="form-control" data-setting="username" placeholder="e.g., janedoe">
                    </div>
                    <div class="form-group">
                        <label for="settings-email">Email address</label>
                        <input type="email" id="settings-email" class="form-control" data-setting="email" placeholder="e.g., jane.doe@example.com">
                        <p class="form-error-message" id="email-error">Please enter a valid email.</p>
                    </div>
                    <div class="form-group">
                        <label for="settings-created-at">Account created</label>
                        <input type="text" id="settings-created-at" class="form-control" disabled>
                    </div>
                </form>
            </div>

            <!-- PERSONALIZATION PANEL — everything here feeds the tutor's prompt
                 (learning_level per message; the rest via server_mysql.php's
                 personalization context). Saves as soon as a field changes. -->
            <div id="tab-personalization" class="tab-panel">
                <h3>How TutorMind teaches you</h3>
                <p>TutorMind uses this quietly to pitch explanations and pick examples. It won't read your profile back to you.</p>
                <div class="form-group">
                    <label for="settings-learning-level">What you're aiming for</label>
                    <select id="settings-learning-level" class="form-select" data-setting="learning_level">
                        <option value="Remember">Remember the key facts</option>
                        <option value="Understand">Understand how it works</option>
                        <option value="Apply">Use it to solve problems</option>
                        <option value="Analyze">Break problems down</option>
                        <option value="Evaluate">Judge and compare ideas</option>
                        <option value="Create">Make something new with it</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Explanations</label>
                    <div class="option-group" data-setting="response_style">
                        <button type="button" class="option-btn" data-value="concise">Focused</button>
                        <button type="button" class="option-btn" data-value="detailed">In depth</button>
                    </div>
                    <p class="field-hint">In depth gives fuller explanations with a worked example before your turn.</p>
                </div>

                <h3>About you</h3>
                <div class="form-group">
                    <label for="settings-education-level">Education</label>
                    <select id="settings-education-level" class="form-select" data-setting="education_level">
                        <option value="">Not set</option>
                        <option value="Primary">Primary school</option>
                        <option value="Secondary">Secondary / high school</option>
                        <option value="University">University or college</option>
                        <option value="Graduate">Graduate</option>
                        <option value="Professional">Working professional</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="settings-field-of-study">Subject or field</label>
                    <input type="text" id="settings-field-of-study" class="form-control" data-setting="field_of_study" maxlength="255" placeholder="e.g., Computer Science, Biology">
                </div>
                <div class="form-group">
                    <label>How much you already know</label>
                    <div class="option-group" data-setting="knowledge_level">
                        <button type="button" class="option-btn" data-value="beginner">Beginner</button>
                        <button type="button" class="option-btn" data-value="intermediate">Intermediate</button>
                        <button type="button" class="option-btn" data-value="advanced">Advanced</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="settings-interest-entry">Interests</label>
                    <p class="field-hint">Examples get built around these, like football, music or cooking.</p>
                    <ul class="interest-list" id="settings-interests" data-setting="interests" aria-label="Your interests"></ul>
                    <input type="text" id="settings-interest-entry" class="form-control" maxlength="60" placeholder="Add an interest and press Enter">
                </div>
                <div class="form-group">
                    <label for="settings-country">Country</label>
                    <input type="text" id="settings-country" class="form-control" data-setting="country" maxlength="100" placeholder="e.g., Ghana" autocomplete="country-name">
                    <p class="field-hint">Used for local currency, units and spelling in examples.</p>
                </div>
                <div class="form-group">
                    <label for="settings-primary-language">Main language</label>
                    <input type="text" id="settings-primary-language" class="form-control" data-setting="primary_language" maxlength="50" placeholder="English">
                </div>
            </div>

            <!-- SECURITY PANEL -->
            <div id="tab-security" class="tab-panel">
                <h3>Change password</h3>
                <p>Use a long password you don't use anywhere else.</p>
                <form id="password-form" novalidate>
                    <div class="form-group">
                        <label for="settings-current-password">Current password</label>
                        <div class="input-group">
                            <input type="password" id="settings-current-password" class="form-control" autocomplete="current-password">
                            <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="settings-new-password">New password</label>
                        <div class="input-group">
                            <input type="password" id="settings-new-password" class="form-control" autocomplete="new-password">
                             <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
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
                        <label for="settings-confirm-password">Confirm new password</label>
                        <div class="input-group">
                            <input type="password" id="settings-confirm-password" class="form-control" autocomplete="new-password">
                             <div class="input-group-append">
                                <button class="btn-icon password-toggle" type="button" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <p class="form-error-message" id="confirm-password-error">Passwords do not match.</p>
                    </div>
                    <button type="submit" class="btn-save" id="change-password-btn">Update password</button>
                </form>
            </div>

            <!-- NOTIFICATIONS PANEL -->
            <div id="tab-notifications" class="tab-panel">
                <h3>Reminders</h3>
                <p>Choose how TutorMind nudges you to keep learning.</p>
                <!-- notifications_enabled is what scripts/send_study_reminders.php reads;
                     the old study_reminders / email / weekly-summary columns had no sender. -->
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Study reminders</h4>
                        <p>Get reminded to study on the schedule you picked during setup.</p>
                    </div>
                    <label class="switch"><input type="checkbox" role="switch" aria-label="Study reminders" data-setting="notifications_enabled"><span class="slider"></span></label>
                </div>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Push notifications</h4>
                        <p>Get reminders on this device, even when TutorMind isn't open.</p>
                    </div>
                    <label class="switch"><input type="checkbox" role="switch" aria-label="Push notifications" id="settings-push-toggle"><span class="slider"></span></label>
                </div>
            </div>

            <!-- APPEARANCE PANEL -->
            <div id="tab-appearance" class="tab-panel">
                <h3>Theme and text</h3>
                <p>How TutorMind looks on this account.</p>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Dark mode</h4>
                        <p>Easier on the eyes in low light.</p>
                    </div>
                    <label class="switch"><input type="checkbox" role="switch" aria-label="Dark mode" id="settings-dark-mode" data-setting="dark_mode"><span class="slider"></span></label>
                </div>
                <div class="form-group">
                    <label>Chat density</label>
                    <div class="option-group" data-setting="chat_density">
                        <button type="button" class="option-btn" data-value="compact">Compact</button>
                        <button type="button" class="option-btn active" data-value="comfortable">Comfortable</button>
                    </div>
                </div>

                <!-- One text-size control. The old Font Size buttons (root font-size)
                     did the same job less well, so they're gone and no longer applied. -->
                <div class="form-group legibility-slider-group">
                    <label for="settings-legibility">
                        Text size
                        <span class="legibility-value" id="legibility-value">100%</span>
                        <button type="button" class="legibility-reset" id="legibility-reset" hidden>Reset</button>
                    </label>
                    <input type="range" id="settings-legibility" class="legibility-slider"
                           min="90" max="150" value="100" step="5" data-setting="legibility">
                    <div class="legibility-hint">
                        <span>Smaller</span>
                        <span>Larger</span>
                    </div>
                    <p class="legibility-description">Scales chat text and line spacing together for easier reading.</p>
                </div>
            </div>

            <!-- PRIVACY PANEL -->
            <div id="tab-privacy" class="tab-panel">
                <h3>Privacy</h3>
                <div class="toggle-group">
                    <div class="toggle-label">
                        <h4>Help improve TutorMind</h4>
                        <p>Allow us to use anonymized data to improve our AI.</p>
                    </div>
                    <label class="switch"><input type="checkbox" role="switch" aria-label="Help improve TutorMind" data-setting="data_sharing"><span class="slider"></span></label>
                </div>

                <div class="danger-zone">
                    <h4>Your data</h4>
                    <div class="action-item">
                        <div class="action-item-label">
                            <p>Permanently delete all of your conversations.</p>
                        </div>
                        <button type="button" class="btn-danger" id="clear-history-btn">Clear history</button>
                    </div>
                    <div class="action-item">
                        <div class="action-item-label">
                            <p>Permanently delete your account and everything in it.</p>
                        </div>
                        <button type="button" class="btn-danger" id="delete-account-btn">Delete account</button>
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

        // Account text fields: the only edits that wait for "Save" (Enter or Ctrl+S also save)
        this.modal.querySelector('#settings-save-btn').addEventListener('click', this.saveTextInputs);
        this.modal.querySelector('#account-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveTextInputs();
        });
        this.modal.querySelector('#account-form').addEventListener('input', (e) => {
            if (this.isPopulating || !e.target.matches('input[data-setting]')) return;
            this.setDirty(this.accountChanges() !== null);
        });

        // Personalization text fields save when you leave them (or press Enter)
        this.modal.querySelectorAll('#tab-personalization input[data-setting]').forEach(input => {
            input.addEventListener('change', () => {
                if (this.isPopulating) return;
                this.debouncedSave({ [input.dataset.setting]: input.value.trim() });
            });
        });
        const interestEntry = this.modal.querySelector('#settings-interest-entry');
        interestEntry.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const value = interestEntry.value.trim();
            const exists = this.interests.some(i => i.toLowerCase() === value.toLowerCase());
            if (value && !exists && this.interests.length < 20) this.saveInterests([...this.interests, value]);
            else if (this.interests.length >= 20) this.showToast('You can add up to 20 interests.', 'info');
            interestEntry.value = '';
        });

        // Dropdowns (learning level, education) save as soon as they change
        this.modal.querySelectorAll('select[data-setting]').forEach(select => {
            select.addEventListener('change', () => {
                if (this.isPopulating) return;
                this.debouncedSave({ [select.dataset.setting]: select.value });
            });
        });

        // Toggle switch auto-saving
        this.modal.querySelectorAll('.switch input[type="checkbox"]').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const setting = e.target.dataset.setting;
                if (!setting) return; // Not a users-column toggle (e.g. the push-notifications toggle below)
                const value = e.target.checked;
                this.debouncedSave({ [setting]: value });

                // Special case for dark mode to apply immediately
                if (setting === 'dark_mode') {
                    document.body.classList.toggle('dark-mode', value);
                    // Sync with the main toggle in the user menu
                    const mainToggle = document.getElementById('darkModeToggle');
                    if (mainToggle) mainToggle.checked = value;
                    localStorage.setItem('tutormind-theme', value ? 'dark' : 'light');
                }
            });
        });

        // Push notifications toggle (per-browser subscription, not a users column)
        const pushToggle = this.modal.querySelector('#settings-push-toggle');
        if (pushToggle) {
            pushToggle.addEventListener('change', async (e) => {
                const enabling = e.target.checked;
                try {
                    if (enabling) {
                        await subscribeToPush();
                        this.showToast('Push notifications enabled!', 'success');
                    } else {
                        await unsubscribeFromPush();
                        this.showToast('Push notifications disabled.', 'success');
                    }
                } catch (error) {
                    console.error('Push toggle error:', error);
                    this.showToast('Error: ' + error.message, 'error');
                    e.target.checked = !enabling; // Revert on failure
                }
            });
        }

        // Appearance option buttons (font size, density): apply and save right away
        this.modal.querySelectorAll('.option-group').forEach(group => {
            group.addEventListener('click', e => {
                const btn = e.target.closest('.option-btn');
                if (!btn) return;
                const change = { [group.dataset.setting]: btn.dataset.value };
                this.updateOptionButtons(group, btn.dataset.value);
                this.applyGlobalSettings(change);
                this.debouncedSave(change);
            });
        });

        // Legibility slider: live preview while dragging, saved on release
        const legibilitySlider = this.modal.querySelector('#settings-legibility');
        if (legibilitySlider) {
            legibilitySlider.addEventListener('input', (e) => {
                this.applyLegibility(e.target.value);
            });
            legibilitySlider.addEventListener('change', (e) => {
                const value = parseInt(e.target.value, 10);
                this.debouncedSave({ legibility: value });
                localStorage.setItem('legibility', value);
            });
            this.modal.querySelector('#legibility-reset').addEventListener('click', () => {
                this.applyLegibility(100);
                this.debouncedSave({ legibility: 100 });
                localStorage.setItem('legibility', 100);
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
        // (the button sits in .input-group-append, so its input isn't a sibling)
        securityTab.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.closest('.input-group').querySelector('input');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', String(show));
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.querySelector('i').classList.replace(show ? 'fa-eye' : 'fa-eye-slash', show ? 'fa-eye-slash' : 'fa-eye');
            });
        });

        // Password strength indicator
        const newPasswordInput = securityTab.querySelector('#settings-new-password');
        newPasswordInput.addEventListener('input', () => this.updatePasswordStrength(newPasswordInput.value));

        // Change password (a real submit, so Enter works; the page must not reload)
        securityTab.querySelector('#password-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.changePassword();
        });
    }

    /**
     * Attaches event listeners specific to the Privacy tab.
     */
    attachPrivacyListeners() {
        this.modal.querySelector('#delete-account-btn').addEventListener('click', async () => {
            const password = await TmDialog.prompt({
                title: 'Delete your account?',
                message: 'This permanently erases your account and all of your chats. Enter your password to confirm.',
                inputType: 'password',
                inputLabel: 'Password',
                destructive: true,
                confirmLabel: 'Delete account'
            });
            if (password) this.deleteAccount(password);
        });

        this.modal.querySelector('#clear-history-btn').addEventListener('click', async () => {
            const ok = await TmDialog.confirm({
                title: 'Clear all chat history?',
                message: 'This permanently deletes all of your conversations. It can’t be undone.',
                destructive: true,
                confirmLabel: 'Clear history'
            });
            if (ok) this.clearChatHistory();
        });
    }

    /**
     * Opens the settings modal. Renders from the copy loaded at page start
     * (no loader), then refreshes it quietly in the background.
     */
    open() {
        document.addEventListener('keydown', this.handleKeyDown);
        this.modal.classList.remove('hidden'); // This removes display: none !important;
        this.modal.querySelector('.tab-btn').focus();
        if (this.loaded) {
            this.populateForm(this.initialSettings);
            this.loadSettings({ quiet: true });
        } else {
            this.loadSettings();
        }
    }

    /**
     * Closes the settings modal.
     */
    async close() {
        if (this.dirty) {
            const ok = await TmDialog.confirm({
                title: 'Unsaved Changes',
                message: 'You have unsaved changes. Are you sure you want to close?',
                confirmLabel: 'Discard Changes',
                cancelLabel: 'Keep Editing',
                destructive: true
            });
            if (!ok) return;
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
        // A TmDialog on top handles its own Escape
        if (e.key === 'Escape' && !document.querySelector('.tm-dialog-overlay')) {
            this.close();
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (this.dirty) this.saveTextInputs();
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

        // Only the Account tab has fields that wait for "Save"; everything else saves instantly
        this.modal.querySelector('#settings-save-btn').hidden = tabName !== 'account';
    }

    /**
     * Fetches the current user settings from the API and populates the form.
     * quiet: refresh in the background (no loader); only re-render if the
     * server copy changed and the user isn't mid-edit.
     */
    async loadSettings({ quiet = false } = {}) {
        if (!quiet) this.showLoadingState(true);
        try {
            // The chat starts a background load on page load; opening Settings
            // meanwhile joins that request rather than firing a second one.
            if (!this.inflight) {
                this.inflight = this.fetchSettings().finally(() => { this.inflight = null; });
            }
            const changed = await this.inflight;
            if (!quiet || (changed && !this.dirty)) {
                this.populateForm(this.initialSettings);
                this.applyGlobalSettings(this.initialSettings);
            }
        } catch (error) {
            console.error('Settings load error:', error);
            if (!quiet) this.showToast('Error: ' + error.message, 'error');
        } finally {
            if (!quiet) this.showLoadingState(false);
        }
    }

    /**
     * GETs the server copy into initialSettings. Changes made here that the
     * server hasn't confirmed yet (this.unsaved) sit on top: a response that
     * was already on its way when the user flipped something must not flip it
     * back. Resolves to whether the result differs from what we had.
     */
    async fetchSettings() {
        const response = await fetch('api/user_settings.php');
        if (!response.ok) throw new Error('Failed to load settings.');

        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Unknown error loading settings.');

        const settings = { ...data.settings, ...this.unsaved };
        const changed = JSON.stringify(settings) !== JSON.stringify(this.initialSettings);
        this.initialSettings = settings;
        this.loaded = true;
        return changed;
    }

    /**
     * Applies settings to the global application UI (outside the modal).
     * @param {object} settings The settings object.
     */
    applyGlobalSettings(settings) {
        // Apply Learning Level to the main chat input
        const mainLearningLevel = document.getElementById('learningLevel');
        if (mainLearningLevel && settings.learning_level) {
            mainLearningLevel.value = settings.learning_level;
        }

        // Apply Chat Density
        if (settings.chat_density) {
            document.body.classList.toggle('compact-mode', settings.chat_density === 'compact');
        }
        
        // Apply Dark Mode and sync with localStorage
        if (settings.dark_mode !== undefined) {
             const isDark = !!settings.dark_mode;
             document.body.classList.toggle('dark-mode', isDark);
             const mainToggle = document.getElementById('darkModeToggle');
             if (mainToggle) mainToggle.checked = isDark;
             // Update localStorage to match database setting
             localStorage.setItem('tutormind-theme', isDark ? 'dark' : 'light');
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
        const reset = document.getElementById('legibility-reset');
        if (reset) reset.hidden = Number(value) === 100;
    }

    /**
     * Populates the form fields with data from the API.
     * @param {object} settings The user settings object.
     */
    populateForm(settings) {
        // Set flag to prevent input events from marking form as dirty
        this.isPopulating = true;
        
        // Text inputs, selects, checkboxes, option groups, interest chips
        this.modal.querySelectorAll('[data-setting]').forEach(el => {
            const key = el.dataset.setting;
            if (settings.hasOwnProperty(key)) this.setControl(el, settings[key]);
        });
        this.applyLegibility(settings.legibility ?? 100);


        // Read-only fields
        const createdAt = this.modal.querySelector('#settings-created-at');
        if (createdAt && settings.created_at) {
            createdAt.value = new Date(settings.created_at).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            });
        }
        
        // Push toggle state lives in the browser, not in the settings API response
        const pushToggle = this.modal.querySelector('#settings-push-toggle');
        if (pushToggle && 'serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(reg => {
                return reg ? reg.pushManager.getSubscription() : null;
            }).then(sub => {
                pushToggle.checked = !!sub;
            }).catch(err => console.error('Failed to check push subscription state:', err));
        }

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
        // Settled (saved or reverted): loads may use the server's value again — unless
        // the same setting was changed once more while this save was out.
        const settle = () => Object.keys(settings).forEach(key => {
            if (this.unsaved[key] === settings[key]) delete this.unsaved[key];
        });
        try {
            const response = await fetch('api/user_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
            const result = await response.json();
            if (!result.success) throw new Error(result.error || 'Failed to save.');
            settle();
            this.showToast('Setting saved!', 'success');
            this.applyGlobalSettings(settings);
            Object.assign(this.initialSettings, settings);
        } catch (error) {
            settle();
            console.error('saveToggle error:', error);
            this.showToast('Error: ' + error.message, 'error');
            // Put every control in the failed batch back to its last saved value
            Object.keys(settings).forEach(key => {
                const el = this.modal.querySelector(`[data-setting="${key}"]`);
                if (el && key in this.initialSettings) this.setControl(el, this.initialSettings[key]);
            });
            this.applyGlobalSettings(this.initialSettings);
        }
    }

    /**
     * Shows a saved value in its control, whatever kind of control it is.
     */
    setControl(el, value) {
        if (el.matches('input[type="checkbox"]')) el.checked = !!value;
        else if (el.classList.contains('option-group')) this.updateOptionButtons(el, value);
        else if (el.classList.contains('interest-list')) this.renderInterests(Array.isArray(value) ? value : []);
        else el.value = value ?? ''; // NULL columns (e.g. last_name) must not render as "null"
    }

    /**
     * Interests are edited as chips; any add/remove saves the whole list.
     */
    renderInterests(list) {
        this.interests = list.slice();
        const ul = this.modal.querySelector('#settings-interests');
        ul.innerHTML = '';
        this.interests.forEach(interest => {
            const li = document.createElement('li');
            li.className = 'interest-chip';
            li.textContent = interest;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${interest}`);
            remove.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
            remove.addEventListener('click', () => this.saveInterests(this.interests.filter(i => i !== interest)));
            li.appendChild(remove);
            ul.appendChild(li);
        });
    }

    saveInterests(list) {
        this.renderInterests(list);
        this.debouncedSave({ interests: this.interests });
    }

    /**
     * The Account text fields that differ from the saved copy, or null if none do.
     */
    accountChanges() {
        const payload = {};
        this.modal.querySelectorAll('#account-form input[data-setting]').forEach(el => {
            const key = el.dataset.setting;
            const value = el.value.trim();
            if (value !== (this.initialSettings[key] ?? '')) payload[key] = value;
        });
        return Object.keys(payload).length ? payload : null;
    }

    setDirty(dirty) {
        this.dirty = dirty;
        this.modal.querySelector('#settings-save-btn').disabled = !dirty;
    }

    /**
     * Saves the Account text fields (name, username, email).
     */
    async saveTextInputs() {
        const payload = this.accountChanges();
        if (!payload) { this.setDirty(false); return; }

        const emailInput = this.modal.querySelector('#settings-email');
        const emailError = this.modal.querySelector('#email-error');
        const emailOk = !('email' in payload) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email);
        emailInput.classList.toggle('is-invalid', !emailOk);
        emailError.style.display = emailOk ? 'none' : 'block';
        if (!emailOk) { emailInput.focus(); return; }

        const saveBtn = this.modal.querySelector('#settings-save-btn');
        saveBtn.classList.add('loading');
        saveBtn.disabled = true;
        try {
            const response = await fetch('api/user_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!result.success) throw new Error(result.error || 'Failed to save.');
            Object.assign(this.initialSettings, payload);
            this.updateMainUI(payload);
            this.showToast('Settings saved successfully!', 'success');
            this.setDirty(false);
        } catch (error) {
            this.showToast('Error: ' + error.message, 'error');
            saveBtn.disabled = false; // keep the edits so they can be fixed and retried
        } finally {
            saveBtn.classList.remove('loading');
        }
    }

    /**
     * Updates the main UI (sidebar) with new user details after a save.
     * @param {object} updatedSettings An object with the changed settings.
     */
    updateMainUI(updatedSettings) {
        const { first_name, email, username } = updatedSettings;

        if (first_name !== undefined || username !== undefined) {
            // Same rule as tutor_mysql.php's $displayName: first name, else username
            const displayName = this.initialSettings.first_name || this.initialSettings.username || 'User';
            document.querySelectorAll('.user-details h4').forEach(el => {
                el.textContent = displayName;
            });
            // Letter avatars only; a Google photo avatar stays as it is
            document.querySelectorAll('.user-avatar').forEach(el => {
                if (!el.querySelector('img')) el.textContent = displayName.charAt(0).toUpperCase();
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
            const on = btn.dataset.value === value;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', String(on));
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
        changeBtn.disabled = true;
        changeBtn.textContent = 'Updating…';

        try {
            // Fetch CSRF token before submitting
            const tokenResponse = await fetch('includes/csrf.php?action=get_token');
            const tokenData = await tokenResponse.json();

            // auth_mysql.php reads the action from the POST body, not the query string
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('csrf_token', tokenData.token);

            const response = await fetch('auth_mysql.php', {
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
            changeBtn.disabled = false;
            changeBtn.textContent = 'Update password';
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
        this.showToast('Clearing history… please wait.', 'info');
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
        // Empty field: empty meter (it used to fall through to "Medium")
        if (!password) { strengthText.textContent = ''; return; }

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

    // --- UTILITY & HELPER METHODS ---

    showToast(message, type = 'info') {
        const toast = document.getElementById('copy-toast');
        if (!toast) return toast;
        // Remove previous type classes & clear any pending hide timer
        toast.classList.remove('toast-success', 'toast-error', 'toast-warning', 'toast-info');
        toast.classList.add(`toast-${type}`);
        // Remove any inline background so CSS class wins
        toast.style.removeProperty('background-color');
        toast.textContent = message;
        toast.style.display = 'block';
        toast.style.opacity = '1';
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.style.transition = 'opacity 250ms ease-out';
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.style.display = 'none';
                toast.style.transition = '';
                toast.classList.remove('toast-success', 'toast-error', 'toast-warning', 'toast-info');
            }, 280);
        }, 3500);
        return toast;
    }

    showLoadingState(isLoading) {
        const container = this.modal.querySelector('.settings-container');
        if (isLoading) {
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner-overlay';
            spinner.innerHTML = typeof TmLoader !== 'undefined'
                ? TmLoader.inlineHTML()
                : '<i class="fas fa-spinner fa-spin fa-3x"></i>'; // Fallback if tm-loader.js didn't load
            const svg = spinner.querySelector('svg');
            if (svg) { svg.style.width = '48px'; svg.style.height = '48px'; svg.style.color = 'var(--primary)'; }
            container.appendChild(spinner);
        } else {
            const spinner = container.querySelector('.loading-spinner-overlay');
            if (spinner) spinner.remove();
        }
    }

    resetFormState() {
        this.setDirty(false);
        this.modal.querySelector('#settings-save-btn').classList.remove('loading');
        this.modal.querySelector('#settings-email').classList.remove('is-invalid');
        this.modal.querySelector('#email-error').style.display = 'none';
        this.clearPasswordFields();
        this.clearPasswordErrors();
    }

    clearPasswordFields() {
        this.modal.querySelector('#settings-current-password').value = '';
        this.modal.querySelector('#settings-new-password').value = '';
        this.modal.querySelector('#settings-confirm-password').value = '';
        this.updatePasswordStrength('');
        // Anything left revealed goes back to hidden
        this.modal.querySelectorAll('.password-toggle[aria-pressed="true"]').forEach(btn => btn.click());
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
