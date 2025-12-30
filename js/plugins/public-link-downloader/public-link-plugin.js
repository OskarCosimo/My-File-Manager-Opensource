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

            // Configure download URL: explicit option or auto-detect from connector URL
            if (fileManager.options.publicLinksDownloadUrl) {
                // Use explicit configuration
                this.downloadUrl = fileManager.options.publicLinksDownloadUrl;
            } else {
                // Fallback: auto-detect by replacing 'connector.php' with 'download.php'
                this.downloadUrl = this.apiUrl.replace('connector.php', 'download.php');
            }

            console.log('✅ Public Link Plugin initialized');
            console.log('🔗 Download URL:', this.downloadUrl);

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

            const modalHTML = `
                <div class="mfm-modal-overlay" id="public-link-modal">
                    <div class="mfm-modal-dialog" style="max-width: 600px;">
                        <div class="mfm-modal-header">
                            <h3>🔗 Generate Public Download Link</h3>
                            <button class="mfm-modal-close" id="close-link-modal">×</button>
                        </div>
                        <div class="mfm-modal-body">
                            <p><strong>File:</strong> ${this.escapeHtml(file.name)}</p>
                            <p><strong>Size:</strong> ${fm.formatSize(file.size)}</p>
                            
                            <div style="margin-top: 20px;">
                                <label><strong>Access Type:</strong></label><br>
                                <label><input type="radio" name="linkType" value="public" checked> Public (Anyone with link)</label><br>
                                <label><input type="radio" name="linkType" value="registered"> Registered Users Only</label>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <label><strong>Link Expiration:</strong></label><br>
                                <select id="link-expiration" style="width: 100%; padding: 8px;">
                                    <option value="5">5 minutes</option>
                                    <option value="10">10 minutes</option>
                                    <option value="15">15 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="90">1 hour 30 minutes</option>
                                    <option value="120">2 hours</option>
                                    <option value="180">3 hours</option>
                                </select>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <label><strong>Wait Time (Anti-spam):</strong></label><br>
                                <select id="wait-time" style="width: 100%; padding: 8px;">
                                    <option value="30">30 seconds</option>
                                    <option value="60" selected>1 minute</option>
                                    <option value="120">2 minutes</option>
                                    <option value="180">3 minutes</option>
                                    <option value="300">5 minutes</option>
                                </select>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <label><input type="checkbox" id="max-downloads"> Limit downloads</label>
                                <input type="number" id="max-downloads-count" min="1" value="10" style="width: 80px; margin-left: 10px;" disabled>
                            </div>
                            
                            <div style="margin-top: 25px; text-align: right;">
                                <button class="mfm-btn" id="cancel-link-btn">Cancel</button>
                                <button class="mfm-btn" style="background: #4CAF50; color: white; margin-left: 10px;" 
                                        id="generate-link-btn">Generate Link</button>
                            </div>
                            
                            <div id="generated-link-container" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px;">
                                <p><strong>✅ Link Generated!</strong></p>
                                <input type="text" id="generated-link-url" readonly style="width: 100%; padding: 8px; margin-top: 10px;">
                                <button class="mfm-btn" style="margin-top: 10px; width: 100%;" id="copy-link-btn">
                                    📋 Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
                    const isExpired = expiresDate < now;
                    const linkTypeLabel = link.link_type === 'public' ? '🌐 Public' : '🔒 Registered';

                    linksHTML += '<tr style="border-bottom: 1px solid #eee;">';
                    linksHTML += `<td style="padding: 10px;">${self.escapeHtml(link.file_name)}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">${linkTypeLabel}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">${link.download_count}${link.max_downloads > 0 ? '/' + link.max_downloads : ''}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center; color: ${isExpired ? 'red' : 'inherit'}">${expiresDate.toLocaleString()}</td>`;
                    linksHTML += `<td style="padding: 10px; text-align: center;">
                        <button class="mfm-btn link-copy-btn" data-token="${link.token}" style="font-size: 12px; padding: 5px 10px;">Copy</button>
                        <button class="mfm-btn mfm-btn-danger link-delete-btn" data-token="${link.token}" style="font-size: 12px; padding: 5px 10px; margin-left: 5px; background: #f44336;">Delete</button>
                    </td>`;
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