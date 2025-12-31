/**
 * Public Link Generator Plugin for MyFileManager
 */
(function () {
    'use strict';

    const PublicLinkPlugin = {
        name: 'PublicLinkGenerator',
        version: '1.0.0',
        activeModal: null,
        downloadUrl: '/download.php', // Default fallback

        init: function (fileManager) {
            this.fm = fileManager;
            this.apiUrl = fileManager.options.url;

            // Configure cancelJsonFile option (default: true for backward compatibility)
            this.cancelJsonFile = fileManager.options.publicLinksCancelJsonFile !== undefined
                ? fileManager.options.publicLinksCancelJsonFile
                : true;

            // Configure available expiration times (in minutes)
            // Default: all options available
            this.availableExpirations = fileManager.options.publicLinksExpirations || [
                30,    // 30 minutes
                60,    // 1 hour
                120,   // 2 hours
                180,   // 3 hours
                360,   // 6 hours
                720,   // 12 hours
                1440,  // 24 hours (1 day)
                2160,  // 36 hours
                2880   // 48 hours (2 days)
            ];

            // Configure available wait times (in seconds)
            // Default: all options available
            this.availableWaitTimes = fileManager.options.publicLinksWaitTimes || [
                0,     // No wait
                10,    // 10 seconds
                30,    // 30 seconds
                60,    // 1 minute
                120,   // 2 minutes
                300    // 5 minutes
            ];

            // Configure download URL
            if (fileManager.options.publicLinksDownloadUrl) {
                this.downloadUrl = fileManager.options.publicLinksDownloadUrl;
            } else {
                this.downloadUrl = this.apiUrl.replace('connector.php', 'download.php');
            }

            console.log('✅ Public Link Plugin initialized');
            console.log('🔗 Download URL:', this.downloadUrl);
            console.log('🗑️ Cancel JSON on expiration:', this.cancelJsonFile);
            console.log('⏱️ Available expirations:', this.availableExpirations);
            console.log('⏳ Available wait times:', this.availableWaitTimes);

            // ensure customContextMenu exists
            if (!this.fm.options.customContextMenu) {
                this.fm.options.customContextMenu = [];
            }

            // add "Generate Public Link" to context menu
            this.fm.options.customContextMenu.push({
                id: 'generate-public-link',
                label: '🔗 Generate Public Link',
                action: 'generatePublicLink',
                icon: '🔗',
                condition: function (selectedFiles) {
                    return selectedFiles.length === 1 && selectedFiles[0].mime !== 'directory';
                },
                handler: this.showLinkGenerator.bind(this)
            });

            // add "Manage Links" to File menu
            this.addManageLinksToFileMenu();
        },

        /**
         * Add "Manage Links" to File menu
         */
        addManageLinksToFileMenu: function () {
            const self = this;

            // Delay to ensure menu is rendered
            setTimeout(function () {
                const fileMenu = document.querySelector('[data-menu="file"]');
                if (!fileMenu) {
                    console.warn('⚠️ File menu not found, retrying...');
                    setTimeout(arguments.callee, 100);
                    return;
                }

                const fileMenuDropdown = fileMenu.querySelector('.mfm-menu-dropdown');
                if (!fileMenuDropdown) {
                    console.warn('⚠️ File menu dropdown not found');
                    return;
                }

                // create separator and menu item
                const separator = document.createElement('div');
                separator.className = 'mfm-menu-separator';

                const menuItem = document.createElement('div');
                menuItem.className = 'mfm-menu-item';
                menuItem.setAttribute('data-action', 'managePublicLinks');
                menuItem.title = 'Manage Public Links';
                menuItem.innerHTML = '<span class="mfm-menu-item-icon">📊</span><span>Manage Public Links</span>';

                // add event listener
                menuItem.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.showLinkManager([], self.fm);

                    // close all dropdowns
                    const allDropdowns = document.querySelectorAll('.mfm-menu-dropdown');
                    allDropdowns.forEach(dd => dd.style.display = 'none');
                });

                // append to menu
                fileMenuDropdown.appendChild(separator);
                fileMenuDropdown.appendChild(menuItem);

                console.log('✅ "Manage Links" added to File menu');
            }, 500);
        },

        /**
         * Close any existing modal
         */
        closeActiveModal: function () {
            if (this.activeModal) {
                this.activeModal.remove();
                this.activeModal = null;
            }
        },

        /**
         * Show link generator modal
         */
        showLinkGenerator: function (selectedFiles, fm) {
            this.closeActiveModal();
            const file = selectedFiles[0];
            const self = this;

            // Generate expiration options dynamically
            const expirationLabels = {
                30: '30 minutes',
                60: '1 hour',
                120: '2 hours',
                180: '3 hours',
                360: '6 hours',
                720: '12 hours',
                1440: '24 hours (1 day)',
                2160: '36 hours',
                2880: '48 hours (2 days)'
            };

            let expirationOptionsHTML = '';
            this.availableExpirations.forEach((minutes, index) => {
                const label = expirationLabels[minutes] || `${minutes} minutes`;
                const selected = index === 0 ? 'selected' : '';
                expirationOptionsHTML += `<option value="${minutes}" ${selected}>${label}</option>`;
            });

            // Generate wait time options dynamically
            const waitTimeLabels = {
                0: 'No wait',
                10: '10 seconds',
                30: '30 seconds',
                60: '1 minute',
                120: '2 minutes',
                300: '5 minutes'
            };

            let waitTimeOptionsHTML = '';
            this.availableWaitTimes.forEach((seconds, index) => {
                const label = waitTimeLabels[seconds] || `${seconds} seconds`;
                const selected = (seconds === 30) ? 'selected' : '';
                waitTimeOptionsHTML += `<option value="${seconds}" ${selected}>${label}</option>`;
            });

            const modalHTML = `
        <div class="mfm-modal-overlay" id="public-link-modal">
            <div class="mfm-modal-dialog" style="max-width: 650px;">
                <div class="mfm-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600;">🔗 Generate Public Link</h3>
                    <button class="mfm-modal-close" id="close-link-modal" style="color: white; font-size: 28px; background: transparent; border: none; cursor: pointer; opacity: 0.9;">×</button>
                </div>
                <div class="mfm-modal-body" style="padding: 25px; background: #f8f9fa;">
                    
                    <!-- File Info Section -->
                    <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px;">📁</span>
                            <div style="flex: 1;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Selected File</div>
                                <div style="font-weight: 600; color: #333; word-break: break-all;">${this.escapeHtml(file.name)}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Link Type Section -->
                    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; font-weight: 600;">
                            🔒 Link Access Type
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <label style="display: flex; align-items: center; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="link-type-option">
                                <input type="radio" name="linkType" value="public" checked style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: #333; margin-bottom: 2px;">🌐 Public</div>
                                    <div style="font-size: 11px; color: #888;">Anyone with link</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="link-type-option">
                                <input type="radio" name="linkType" value="registered" style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                                <div>
                                    <div style="font-weight: 600; color: #333; margin-bottom: 2px;">🔐 Registered</div>
                                    <div style="font-size: 11px; color: #888;">Login required</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Settings Section -->
                    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; font-weight: 600;">
                            ⚙️ Link Settings
                        </div>
                        
                        <!-- Expiration -->
                        <div style="margin-bottom: 18px;">
                            <label for="link-expiration" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏱️ Link Expiration
                            </label>
                            <div style="position: relative;">
                                <select id="link-expiration" style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px; transition: all 0.2s;">
                                    ${expirationOptionsHTML}
                                </select>
                            </div>
                        </div>
                        
                        <!-- Wait Time -->
                        <div style="margin-bottom: 18px;">
                            <label for="wait-time" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏳ Wait Time Before Download
                            </label>
                            <div style="position: relative;">
                                <select id="wait-time" style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px; transition: all 0.2s;">
                                    ${waitTimeOptionsHTML}
                                </select>
                            </div>
                        </div>
                        
                        <!-- Max Downloads -->
                        <div>
                            <label style="display: flex; align-items: center; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" id="max-downloads" style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer;">
                                📊 Limit Maximum Downloads
                            </label>
                            <input type="number" id="max-downloads-count" value="10" min="1" disabled 
                                   style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.2s;">
                        </div>
                    </div>

                    <!-- Generated Link Section -->
                    <div id="generated-link-container" style="display: none; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);">
                        <div style="font-size: 13px; color: rgba(255,255,255,0.9); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-weight: 600;">
                            ✅ Link Generated Successfully
                        </div>
                        <input type="text" id="generated-link-url" readonly 
                               style="width: 100%; padding: 12px 15px; border: none; border-radius: 6px; font-size: 13px; background: rgba(255,255,255,0.95); color: #333; margin-bottom: 12px; font-family: monospace;">
                        <button id="copy-link-btn" style="width: 100%; padding: 12px; background: white; color: #11998e; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s;">
                            📋 Copy Link to Clipboard
                        </button>
                    </div>
                </div>
                
                <div class="mfm-modal-footer" style="padding: 20px 25px; background: white; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end; border-radius: 0 0 8px 8px;">
                    <button id="cancel-link-btn" style="padding: 12px 24px; background: #f5f5f5; color: #666; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                        Cancel
                    </button>
                    <button id="generate-link-btn" style="padding: 12px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                        Generate Link
                    </button>
                </div>
            </div>
        </div>
        
        <style>
            .link-type-option:hover {
                border-color: #667eea !important;
                background: #f8f9ff !important;
            }
            
            .link-type-option:has(input:checked) {
                border-color: #667eea !important;
                background: #f0f2ff !important;
            }
            
            #link-expiration:hover,
            #wait-time:hover,
            #max-downloads-count:not(:disabled):hover {
                border-color: #667eea !important;
            }
            
            #link-expiration:focus,
            #wait-time:focus,
            #max-downloads-count:focus {
                outline: none;
                border-color: #667eea !important;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            #max-downloads-count:disabled {
                background: #f5f5f5;
                cursor: not-allowed;
                opacity: 0.6;
            }
            
            #cancel-link-btn:hover {
                background: #e8e8e8 !important;
            }
            
            #generate-link-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5) !important;
            }
            
            #generate-link-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }
            
            #copy-link-btn:hover {
                background: rgba(255,255,255,0.9) !important;
                transform: translateY(-1px);
            }
        </style>
    `;

            this.currentFile = file;
            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;

            // Bind events
            document.getElementById('close-link-modal').addEventListener('click', () => this.closeActiveModal());
            document.getElementById('cancel-link-btn').addEventListener('click', () => this.closeActiveModal());
            document.getElementById('generate-link-btn').addEventListener('click', () => this.generateLink());
            document.getElementById('copy-link-btn').addEventListener('click', () => this.copyLink());
            document.getElementById('max-downloads').addEventListener('change', function (e) {
                document.getElementById('max-downloads-count').disabled = !e.target.checked;
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    self.closeActiveModal();
                }
            });
        },

        /**
         * Generate public link
         */
        generateLink: async function () {
            const linkType = document.querySelector('input[name="linkType"]:checked').value;
            const expiration = parseInt(document.getElementById('link-expiration').value);
            const waitTime = parseInt(document.getElementById('wait-time').value);
            const maxDownloadsEnabled = document.getElementById('max-downloads').checked;
            const maxDownloads = maxDownloadsEnabled ? parseInt(document.getElementById('max-downloads-count').value) : 0;

            const generateBtn = document.getElementById('generate-link-btn');
            generateBtn.disabled = true;
            generateBtn.textContent = 'Generating...';

            try {
                const formData = new FormData();
                formData.append('cmd', 'publiclink_create');
                formData.append('file_hash', this.currentFile.hash);
                formData.append('file_name', this.currentFile.name);
                formData.append('file_size', this.currentFile.size);
                formData.append('link_type', linkType);
                formData.append('expiration_minutes', expiration);
                formData.append('wait_seconds', waitTime);
                formData.append('max_downloads', maxDownloads);
                formData.append('cancel_json_file', this.cancelJsonFile ? '1' : '0');

                if (this.fm.options.token) {
                    formData.append('token', this.fm.options.token);
                }

                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const text = await response.text();
                console.log('Raw response:', text);

                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid server response. Check console for details.');
                }

                if (result.success) {
                    // Use configured downloadUrl with token from server response
                    const linkUrl = window.location.origin + this.downloadUrl + '?t=' + result.token;
                    document.getElementById('generated-link-url').value = linkUrl;
                    document.getElementById('generated-link-container').style.display = 'block';
                    generateBtn.style.display = 'none';
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('Failed to generate link: ' + error.message);
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate Link';
            }
        },

        /**
         * Copy generated link to clipboard
         */
        copyLink: function () {
            const input = document.getElementById('generated-link-url');
            input.select();
            input.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');
                alert('✅ Link copied to clipboard!');
            } catch (err) {
                navigator.clipboard.writeText(input.value).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(() => {
                    alert('❌ Failed to copy. Please copy manually.');
                });
            }
        },

        /**
         * Show link manager modal
         */
        showLinkManager: function (selectedFiles, fm) {
            this.closeActiveModal();

            const self = this;
            const loadingModal = this.createLoadingModal();
            document.body.appendChild(loadingModal);
            this.activeModal = loadingModal;

            // Fetch links from server
            const formData = new FormData();
            formData.append('cmd', 'publiclink_list');
            if (fm.options.token) {
                formData.append('token', fm.options.token);
            }

            console.log('🔍 Fetching links for current user...');

            fetch(this.apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(res => res.text())
                .then(text => {
                    console.log('Link list response:', text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }

                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load links');
                    }

                    console.log('✅ Loaded ' + data.links.length + ' links');

                    self.closeActiveModal();
                    self.renderLinkManager(data.links);
                })
                .catch(err => {
                    console.error('Load links error:', err);
                    self.closeActiveModal();
                    alert('Failed to load links: ' + err.message);
                });
        },

        /**
         * Create loading modal
         */
        createLoadingModal: function () {
            const modalHTML = `
                <div class="mfm-modal-overlay">
                    <div class="mfm-modal-dialog" style="max-width: 400px; text-align: center;">
                        <div class="mfm-modal-body">
                            <p style="font-size: 48px;">⏳</p>
                            <p>Loading links...</p>
                        </div>
                    </div>
                </div>
            `;
            const div = document.createElement('div');
            div.innerHTML = modalHTML;
            return div.firstElementChild;
        },

        /**
         * Render link manager table
         */
        renderLinkManager: function (links) {
            this.closeActiveModal();

            let linksHTML = '';

            if (links.length === 0) {
                linksHTML = '<p style="text-align: center; color: #999; padding: 40px;">No active links</p>';
            } else {
                linksHTML = '<table style="width: 100%; border-collapse: collapse;">';
                linksHTML += '<thead><tr style="border-bottom: 2px solid #ddd;">';
                linksHTML += '<th style="padding: 10px; text-align: left;">File</th>';
                linksHTML += '<th style="padding: 10px; text-align: center;">Type</th>';
                linksHTML += '<th style="padding: 10px; text-align: center;">Downloads</th>';
                linksHTML += '<th style="padding: 10px; text-align: center;">Expires</th>';
                linksHTML += '<th style="padding: 10px; text-align: center;">Actions</th>';
                linksHTML += '</tr></thead><tbody>';

                const self = this;
                links.forEach(link => {
                    const expiresDate = new Date(link.expires_at * 1000);
                    const now = new Date();
                    const isExpired = link.is_expired || expiresDate < now;
                    const linkTypeLabel = link.link_type === 'public' ? '🌐 Public' : '🔒 Registered';

                    // Stile per i link scaduti
                    const rowStyle = isExpired ? 'background-color: #fff3cd; opacity: 0.75;' : '';
                    const expiredBadge = isExpired ? ' <span style="color: #dc3545; font-weight: bold; font-size: 11px;">[EXPIRED]</span>' : '';

                    linksHTML += `<tr style="border-bottom: 1px solid #eee; ${rowStyle}">`;
                    linksHTML += `<td style="padding: 10px;">${self.escapeHtml(link.file_name)}${expiredBadge}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">${linkTypeLabel}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">${link.download_count}${link.max_downloads > 0 ? '/' + link.max_downloads : ''}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center; color: ${isExpired ? '#dc3545' : 'inherit'}; font-weight: ${isExpired ? 'bold' : 'normal'};">${expiresDate.toLocaleString()}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">`;

                    // Mostra il bottone "Copy" solo se il link non è scaduto
                    if (!isExpired) {
                        linksHTML += `<button class="mfm-btn link-copy-btn" data-token="${link.token}" style="font-size: 12px; padding: 5px 10px;">Copy</button>`;
                    }

                    linksHTML += `<button class="mfm-btn mfm-btn-danger link-delete-btn" data-token="${link.token}" style="font-size: 12px; padding: 5px 10px; margin-left: 5px; background: #f44336;">Delete</button>`;
                    linksHTML += `</td>`;
                    linksHTML += '</tr>';
                });

                linksHTML += '</tbody></table>';
            }

            const modalHTML = `
        <div class="mfm-modal-overlay" id="link-manager-modal">
            <div class="mfm-modal-dialog" style="max-width: 1000px;">
                <div class="mfm-modal-header">
                    <h3>📊 Manage Public Links</h3>
                    <button class="mfm-modal-close" id="close-manager-modal">×</button>
                </div>
                <div class="mfm-modal-body" style="max-height: 500px; overflow-y: auto;">
                    ${linksHTML}
                </div>
            </div>
        </div>
    `;

            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;

            const self = this;
            document.getElementById('close-manager-modal').addEventListener('click', () => this.closeActiveModal());

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    self.closeActiveModal();
                }

                if (e.target.classList.contains('link-copy-btn')) {
                    const token = e.target.getAttribute('data-token');
                    self.copyLinkFromManager(token);
                }

                if (e.target.classList.contains('link-delete-btn')) {
                    const token = e.target.getAttribute('data-token');
                    self.deleteLink(token);
                }
            });
        },

        /**
         * Copy link from manager to clipboard
         */
        copyLinkFromManager: function (token) {
            // Use configured downloadUrl
            const linkUrl = window.location.origin + this.downloadUrl + '?t=' + token;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkUrl).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(() => {
                    this.fallbackCopy(linkUrl);
                });
            } else {
                this.fallbackCopy(linkUrl);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function (text) {
            const input = document.createElement('input');
            input.value = text;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
                alert('✅ Link copied to clipboard!');
            } catch (err) {
                alert('❌ Failed to copy. Link: ' + text);
            }
            document.body.removeChild(input);
        },

        /**
         * Delete a public link
         */
        deleteLink: async function (token) {
            if (!confirm('Delete this link?')) return;

            try {
                const formData = new FormData();
                formData.append('cmd', 'publiclink_delete');
                formData.append('link_token', token);
                formData.append('token', this.fm.options.token);

                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response');
                }

                if (result.success) {
                    alert('✅ Link deleted!');
                    this.showLinkManager([], this.fm);
                } else {
                    throw new Error(result.error || 'Delete failed');
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Failed to delete link: ' + error.message);
            }
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    window.PublicLinkPlugin = PublicLinkPlugin;

    // Auto-register with MyFileManager
    if (window.MyFileManager) {
        const originalInit = MyFileManager.prototype.init;
        MyFileManager.prototype.init = function () {
            originalInit.call(this);
            PublicLinkPlugin.init(this);
        };
    }
})();