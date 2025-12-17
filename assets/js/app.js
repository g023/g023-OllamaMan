/**
 * Ollama Manager - Main Application JavaScript
 * 
 * A comprehensive Aqua-styled dashboard for Ollama server management.
 * Built with jQuery for DOM manipulation and AJAX.
 */

(function($) {
    'use strict';

    // ============================================
    // APPLICATION STATE
    // ============================================
    const App = {
        state: {
            theme: 'aqua',
            connected: false,
            models: [],
            runningModels: [],
            selectedModel: null,
            chatMessages: [],
            chatModel: null,
            chatImages: [], // For vision/multimodal
            chatEventSource: null, // For streaming
            isGenerating: false,
            currentChatId: null,
            systemPrompts: [],
            settings: {},
            windows: {},
            activeWindow: null,
            refreshInterval: null,
            spotlightVisible: false,
            chatHistoryCollapsed: true, // Default to hidden
            chatSidebarWidth: 280, // Default sidebar width
            // Token tracking for performance stats
            streamingTokenCount: 0,
            streamingFirstTokenTime: null,
            streamingCharCount: 0
        },

        // API endpoints
        api: {
            status: 'api/status.php',
            models: 'api/models.php',
            chat: 'api/chat.php',
            chatStream: 'api/chat_stream.php',
            generate: 'api/generate.php',
            embed: 'api/embed.php',
            settings: 'api/settings.php',
            history: 'api/history.php',
            logs: 'api/logs.php',
            prompts: 'api/prompts.php',
            uploads: 'api/uploads.php'
        },

        // JSON Schema Presets
        schemaPresets: {
            person: {
                type: 'object',
                properties: {
                    name: { type: 'string' },
                    age: { type: 'integer' },
                    occupation: { type: 'string' },
                    skills: { type: 'array', items: { type: 'string' } }
                },
                required: ['name', 'age']
            },
            product: {
                type: 'object',
                properties: {
                    name: { type: 'string' },
                    price: { type: 'number' },
                    category: { type: 'string' },
                    features: { type: 'array', items: { type: 'string' } },
                    inStock: { type: 'boolean' }
                },
                required: ['name', 'price']
            },
            summary: {
                type: 'object',
                properties: {
                    title: { type: 'string' },
                    summary: { type: 'string' },
                    keyPoints: { type: 'array', items: { type: 'string' } },
                    sentiment: { type: 'string', enum: ['positive', 'negative', 'neutral'] }
                },
                required: ['title', 'summary', 'keyPoints']
            }
        },

        // Initialize application
        init: function() {
            console.log('🚀 Ollama Manager initializing...');
            
            this.loadSettings();
            this.bindEvents();
            this.initWindows();
            this.initWindowZIndex();
            this.checkServerStatus();
            this.startAutoRefresh();
            this.initKeyboardShortcuts();
            this.loadModels();
            this.loadSystemPrompts();
            this.initChatEnhancements();
            
            // Show dashboard by default
            this.showWindow('dashboard');
            
            console.log('✅ Ollama Manager ready!');
        },

        // Initialize chat enhancements
        initChatEnhancements: function() {
            this.initChatDragDrop();
            this.initChatPaste();
            this.initChatOptionsListeners();
            this.checkVoiceInputAvailability();
            this.initChatSidebarResize();
            this.restoreChatSidebarState();
        },
        
        // Restore chat sidebar state from localStorage
        restoreChatSidebarState: function() {
            const collapsed = localStorage.getItem('chatHistoryCollapsed');
            const width = localStorage.getItem('chatSidebarWidth');
            
            // Default to collapsed (hidden)
            this.state.chatHistoryCollapsed = collapsed !== 'false';
            this.state.chatSidebarWidth = width ? parseInt(width) : 280;
            
            this.updateChatSidebarUI();
        },
        
        // Toggle chat sidebar visibility
        toggleChatSidebar: function() {
            this.state.chatHistoryCollapsed = !this.state.chatHistoryCollapsed;
            localStorage.setItem('chatHistoryCollapsed', this.state.chatHistoryCollapsed);
            this.updateChatSidebarUI();
        },
        
        // Update chat sidebar UI based on state
        updateChatSidebarUI: function() {
            const $sidebar = $('#chat-sidebar');
            const $divider = $('#chat-sidebar-divider');
            const $toggleBtn = $('#chat-history-toggle');
            
            if (this.state.chatHistoryCollapsed) {
                $sidebar.hide();
                $divider.hide();
                $toggleBtn.removeClass('active');
            } else {
                $sidebar.show().css('width', this.state.chatSidebarWidth + 'px');
                $divider.show();
                $toggleBtn.addClass('active');
            }
        },
        
        // Initialize chat sidebar resize functionality
        initChatSidebarResize: function() {
            const self = this;
            const $divider = $('#chat-sidebar-divider');
            const $sidebar = $('#chat-sidebar');
            let isResizing = false;
            let startX = 0;
            let startWidth = 0;
            
            $divider.on('mousedown', function(e) {
                e.preventDefault();
                isResizing = true;
                startX = e.clientX;
                startWidth = $sidebar.width();
                $('body').addClass('resizing');
                $divider.addClass('active');
            });
            
            $(document).on('mousemove', function(e) {
                if (!isResizing) return;
                
                const deltaX = e.clientX - startX;
                let newWidth = startWidth + deltaX;
                
                // Constrain width between 200 and 450
                newWidth = Math.max(200, Math.min(450, newWidth));
                
                $sidebar.css('width', newWidth + 'px');
                self.state.chatSidebarWidth = newWidth;
            });
            
            $(document).on('mouseup', function() {
                if (isResizing) {
                    isResizing = false;
                    $('body').removeClass('resizing');
                    $divider.removeClass('active');
                    localStorage.setItem('chatSidebarWidth', self.state.chatSidebarWidth);
                }
            });
        },
        
        // Check if voice input is available and hide button if not
        checkVoiceInputAvailability: function() {
            const hasAPI = ('webkitSpeechRecognition' in window) || ('SpeechRecognition' in window);
            const isSecureContext = location.protocol === 'https:' || 
                                   location.hostname === 'localhost' || 
                                   location.hostname === '127.0.0.1';
            
            if (!hasAPI || !isSecureContext) {
                // Hide voice button and update hints
                $('#voice-input-btn').hide();
                $('.chat-input-hints').find('span:contains("Voice input")').hide();
                
                // Log why it's disabled
                if (!hasAPI) {
                    console.info('Voice input: Not supported by browser');
                } else if (!isSecureContext) {
                    console.info('Voice input: Requires HTTPS or localhost. Access via http://localhost instead of IP address to enable voice input.');
                }
            }
        },

        initChatOptionsListeners: function() {
            // Temperature slider
            $('#chat-temperature').on('input', function() {
                $('#chat-temp-value').text($(this).val());
            });
            
            // Structured output toggle
            $('#chat-structured-output').on('change', function() {
                $('#chat-schema-section').toggle($(this).is(':checked'));
            });
            
            // Preset selector
            $('#chat-preset-select').on('change', function() {
                const $selected = $(this).find('option:selected');
                const content = $selected.data('content');
                if (content) {
                    $('#chat-system-prompt').val(content);
                }
            });
            
            // Image input handler
            $('#chat-image-input').on('change', function() {
                const files = this.files;
                if (files.length > 0) {
                    App.handleImageUpload(files);
                }
                $(this).val(''); // Reset for future uploads
            });
        },

        // ============================================
        // SETTINGS MANAGEMENT
        // ============================================
        loadSettings: function() {
            $.get(this.api.settings + '?action=get')
                .done((response) => {
                    if (response.success) {
                        this.state.settings = response.data;
                        this.applySettings();
                        
                        // Populate form fields
                        $('#setting-theme').val(response.data.theme || 'aqua');
                        $('#setting-default-model').val(response.data.defaultModel || '');
                        $('#setting-temperature').val(response.data.temperature || 0.7);
                        $('#setting-max-tokens').val(response.data.maxTokens || 8192);
                        $('#setting-auto-refresh').val(response.data.autoRefreshInterval || 30);
                        $('#setting-notifications').prop('checked', response.data.showNotifications !== false);
                        $('#setting-ollama-host').val(response.data.ollamaHost || '192.168.1.134');
                        $('#setting-ollama-port').val(response.data.ollamaPort || '11434');
                    }
                })
                .fail(() => {
                    console.warn('Failed to load settings, using defaults');
                });
        },

        saveSettings: function(settings) {
            return $.ajax({
                url: this.api.settings + '?action=save',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(settings)
            });
        },

        applySettings: function() {
            // Apply theme
            if (this.state.settings.theme === 'dark') {
                $('body').attr('data-theme', 'dark');
            } else {
                $('body').removeAttr('data-theme');
            }
            
            // Apply default model
            if (this.state.settings.defaultModel) {
                this.state.chatModel = this.state.settings.defaultModel;
            }
        },

        // ============================================
        // SERVER STATUS & MONITORING
        // ============================================
        checkServerStatus: function() {
            $.get(this.api.status)
                .done((response) => {
                    if (response.success) {
                        this.state.connected = response.data.server.connected;
                        this.state.runningModels = response.data.models.runningList || [];
                        this.updateStatusDisplay(response.data);
                        this.updateDashboard(response.data);
                    }
                })
                .fail(() => {
                    this.state.connected = false;
                    this.updateStatusDisplay({ server: { connected: false } });
                });
        },

        updateStatusDisplay: function(data) {
            const $indicator = $('.server-status');
            const $menuStatus = $('.menubar-status');
            
            if (data.server.connected) {
                $indicator.removeClass('offline').addClass('online');
                $indicator.find('.status-text').text('Connected');
                $menuStatus.html('<span class="status-dot" style="background:#28A745"></span> Online');
            } else {
                $indicator.removeClass('online').addClass('offline');
                $indicator.find('.status-text').text('Disconnected');
                $menuStatus.html('<span class="status-dot" style="background:#DC3545"></span> Offline');
            }
        },

        updateDashboard: function(data) {
            // Update stats
            $('#stat-models-total').text(data.models.total || 0);
            $('#stat-models-running').text(data.models.running || 0);
            $('#stat-storage').text(data.models.totalSize || '0 B');
            $('#stat-latency').text(data.server.latency ? data.server.latency + 'ms' : '--');
            $('#stat-version').text(data.server.version || '--');
            
            // Update running models list
            this.updateRunningModelsList(data.models.runningList || []);
        },

        updateRunningModelsList: function(models) {
            const $list = $('#running-models-list');
            $list.empty();
            
            if (models.length === 0) {
                $list.html('<div class="empty-state"><p>No models running</p></div>');
                return;
            }
            
            models.forEach(model => {
                $list.append(`
                    <div class="model-item running" data-model="${this.escapeHtml(model.name)}">
                        <div class="model-icon">🤖</div>
                        <div class="model-info">
                            <div class="model-name">${this.escapeHtml(model.name)}</div>
                            <div class="model-size">VRAM: ${model.size_vram_formatted || 'N/A'}</div>
                        </div>
                        <span class="aqua-badge success">Running</span>
                    </div>
                `);
            });
        },

        startAutoRefresh: function() {
            const interval = (this.state.settings.autoRefreshInterval || 30) * 1000;
            
            if (this.state.refreshInterval) {
                clearInterval(this.state.refreshInterval);
            }
            
            this.state.refreshInterval = setInterval(() => {
                this.checkServerStatus();
            }, interval);
        },

        // ============================================
        // MODEL MANAGEMENT
        // ============================================
        loadModels: function() {
            $.get(this.api.models + '?action=list')
                .done((response) => {
                    if (response.success) {
                        this.state.models = response.data.models || [];
                        this.renderModelList();
                        this.updateModelSelectors();
                    }
                })
                .fail((xhr) => {
                    this.showNotification('error', 'Error', 'Failed to load models');
                });
        },

        renderModelList: function() {
            const $list = $('#model-list');
            const searchTerm = $('#model-search').val().toLowerCase();
            
            $list.empty();
            
            const filtered = this.state.models.filter(model => 
                model.name.toLowerCase().includes(searchTerm)
            );
            
            if (filtered.length === 0) {
                $list.html(`
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <div class="empty-state-title">No models found</div>
                        <div class="empty-state-text">Pull a model to get started</div>
                    </div>
                `);
                return;
            }
            
            filtered.forEach(model => {
                const isRunning = this.state.runningModels.some(m => m.name === model.name);
                const isSelected = this.state.selectedModel === model.name;
                
                $list.append(`
                    <div class="model-item ${isRunning ? 'running' : ''} ${isSelected ? 'selected' : ''}" 
                         data-model="${this.escapeHtml(model.name)}">
                        <div class="model-icon">🤖</div>
                        <div class="model-info">
                            <div class="model-name">${this.escapeHtml(model.name)}</div>
                            <div class="model-size">${model.size_formatted}</div>
                        </div>
                        ${isRunning ? '<span class="aqua-badge success">Active</span>' : ''}
                    </div>
                `);
            });
        },

        selectModel: function(modelName) {
            this.state.selectedModel = modelName;
            
            // Update selection UI
            $('.model-item').removeClass('selected');
            $(`.model-item[data-model="${modelName}"]`).addClass('selected');
            
            // Load model details
            this.loadModelDetails(modelName);
        },

        loadModelDetails: function(modelName) {
            const $details = $('#model-details');
            
            $details.html(`
                <div class="flex items-center justify-center p-4">
                    <div class="loading-spinner"></div>
                </div>
            `);
            
            $.get(this.api.models + '?action=show&model=' + encodeURIComponent(modelName))
                .done((response) => {
                    if (response.success) {
                        this.renderModelDetails(response.data);
                    }
                })
                .fail(() => {
                    $details.html(`
                        <div class="empty-state">
                            <div class="empty-state-icon">⚠️</div>
                            <div class="empty-state-title">Failed to load details</div>
                        </div>
                    `);
                });
        },

        renderModelDetails: function(data) {
            const $details = $('#model-details');
            const details = data.details || {};
            
            let parametersHtml = '';
            if (data.parameters && Object.keys(data.parameters).length > 0) {
                parametersHtml = Object.entries(data.parameters).map(([key, value]) => `
                    <div class="model-param">
                        <span class="model-param-key">${this.escapeHtml(key)}</span>
                        <span class="model-param-value">${this.escapeHtml(value)}</span>
                    </div>
                `).join('');
            }
            
            $details.html(`
                <div class="model-detail-header">
                    <div class="model-detail-icon">🤖</div>
                    <div class="model-detail-title">
                        <h2>${this.escapeHtml(data.model)}</h2>
                        <p>${details.family || 'Unknown family'} • ${details.parameter_size || 'Unknown size'}</p>
                    </div>
                </div>
                
                <div class="model-meta-grid">
                    <div class="model-meta-item">
                        <div class="model-meta-label">Format</div>
                        <div class="model-meta-value">${details.format || 'N/A'}</div>
                    </div>
                    <div class="model-meta-item">
                        <div class="model-meta-label">Quantization</div>
                        <div class="model-meta-value">${details.quantization_level || 'N/A'}</div>
                    </div>
                    <div class="model-meta-item">
                        <div class="model-meta-label">Parameters</div>
                        <div class="model-meta-value">${details.parameter_size || 'N/A'}</div>
                    </div>
                    <div class="model-meta-item">
                        <div class="model-meta-label">Family</div>
                        <div class="model-meta-value">${details.family || 'N/A'}</div>
                    </div>
                </div>
                
                ${parametersHtml ? `
                <div class="model-section">
                    <h3>Model Parameters</h3>
                    <div class="model-parameters">
                        ${parametersHtml}
                    </div>
                </div>
                ` : ''}
                
                ${data.template ? `
                <div class="model-section">
                    <h3>Template</h3>
                    <pre class="mono" style="background:#F5F5F5;padding:10px;border-radius:6px;font-size:11px;overflow-x:auto;">${this.escapeHtml(data.template)}</pre>
                </div>
                ` : ''}
                
                <div class="model-actions">
                    <button class="aqua-btn primary" onclick="App.openChatWithModel('${this.escapeHtml(data.model)}')">
                        💬 Chat
                    </button>
                    <button class="aqua-btn" onclick="App.openGenerateWithModel('${this.escapeHtml(data.model)}')">
                        ✨ Generate
                    </button>
                    <button class="aqua-btn" onclick="App.copyModel('${this.escapeHtml(data.model)}')">
                        📋 Copy
                    </button>
                    <button class="aqua-btn danger" onclick="App.deleteModel('${this.escapeHtml(data.model)}')">
                        🗑️ Delete
                    </button>
                </div>
            `);
        },

        updateModelSelectors: function() {
            const options = this.state.models.map(m => 
                `<option value="${this.escapeHtml(m.name)}">${this.escapeHtml(m.name)}</option>`
            ).join('');
            
            $('.model-selector').each(function() {
                const $select = $(this);
                const currentValue = $select.val();
                $select.html('<option value="">Select a model...</option>' + options);
                if (currentValue) $select.val(currentValue);
            });
        },

        pullModel: function() {
            const modelName = prompt('Enter model name to pull (e.g., llama3, mistral, phi):');
            if (!modelName) return;
            
            this.showNotification('info', 'Pulling Model', `Downloading ${modelName}... This may take a while.`);
            
            $.ajax({
                url: this.api.models + '?action=pull',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ model: modelName }),
                timeout: 3600000 // 1 hour timeout
            })
            .done((response) => {
                if (response.success) {
                    this.showNotification('success', 'Model Pulled', `${modelName} downloaded successfully!`);
                    this.loadModels();
                } else {
                    this.showNotification('error', 'Pull Failed', response.error);
                }
            })
            .fail(() => {
                this.showNotification('error', 'Pull Failed', 'Failed to pull model');
            });
        },

        deleteModel: function(modelName) {
            if (!confirm(`Are you sure you want to delete "${modelName}"? This cannot be undone.`)) {
                return;
            }
            
            $.ajax({
                url: this.api.models + '?action=delete',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ model: modelName })
            })
            .done((response) => {
                if (response.success) {
                    this.showNotification('success', 'Model Deleted', `${modelName} has been deleted.`);
                    this.state.selectedModel = null;
                    $('#model-details').html('<div class="empty-state"><div class="empty-state-title">Select a model</div></div>');
                    this.loadModels();
                } else {
                    this.showNotification('error', 'Delete Failed', response.error);
                }
            })
            .fail(() => {
                this.showNotification('error', 'Delete Failed', 'Failed to delete model');
            });
        },

        copyModel: function(sourceName) {
            // Open the Model Creator window with the source model pre-selected
            this.showWindow('modelcreator');
            $('#creator-source-model').val(sourceName);
            this.loadSourceModelForCreator();
            $('#creator-new-name').val(sourceName + '-custom').focus();
        },

        // ============================================
        // MODEL CREATOR
        // ============================================
        
        // Default parameter values for reference
        defaultParams: {
            temperature: 0.8,
            num_ctx: 2048,
            num_predict: -1,
            seed: 0,
            top_k: 40,
            top_p: 0.9,
            min_p: 0.0,
            typical_p: 1.0,
            repeat_penalty: 1.1,
            repeat_last_n: 64,
            presence_penalty: 0.0,
            frequency_penalty: 0.0
        },
        
        // Parameter templates for quick setup
        parameterTemplates: {
            creative: {
                name: 'Creative Writing',
                params: {
                    temperature: 1.2,
                    top_p: 0.95,
                    top_k: 60,
                    repeat_penalty: 1.0,
                    presence_penalty: 0.5
                }
            },
            code: {
                name: 'Code Generation',
                params: {
                    temperature: 0.2,
                    top_p: 0.9,
                    top_k: 10,
                    repeat_penalty: 1.1,
                    seed: 42
                }
            },
            precise: {
                name: 'Precise Answers',
                params: {
                    temperature: 0.1,
                    top_p: 0.85,
                    top_k: 5,
                    seed: 42,
                    repeat_penalty: 1.2
                }
            },
            balanced: {
                name: 'Balanced (Defaults)',
                params: {
                    temperature: 0.8,
                    top_p: 0.9,
                    top_k: 40,
                    repeat_penalty: 1.1
                }
            }
        },
        
        // Store source model parameters for comparison
        sourceModelParams: {},
        
        loadSourceModelForCreator: function() {
            const modelName = $('#creator-source-model').val();
            if (!modelName) {
                this.showNotification('warning', 'Select Model', 'Please select a source model first');
                return;
            }
            
            const $sourceParams = $('#creator-source-params');
            $sourceParams.html('<div class="loading-spinner" style="margin: 40px auto;"></div>');
            
            $.get(this.api.models + '?action=show&model=' + encodeURIComponent(modelName))
                .done((response) => {
                    if (response.success) {
                        this.sourceModelParams = response.data.parameters || {};
                        this.sourceModelData = response.data; // Store full model data
                        
                        // Debug logging
                        console.log('Model data:', response.data);
                        console.log('Parameters:', this.sourceModelParams);
                        console.log('Modelfile:', response.data.modelfile);
                        
                        // Parse stop sequences from modelfile
                        if (response.data.modelfile) {
                            const stopSequences = this.parseStopSequencesFromModelfile(response.data.modelfile);
                            if (stopSequences.length > 0) {
                                this.sourceModelParams.stop = stopSequences;
                            }
                        }
                        
                        // Extract template from modelfile
                        if (response.data.modelfile && !this.sourceModelParams.template) {
                            const templateMatch = response.data.modelfile.match(/TEMPLATE\s+"""([\s\S]*?)"""/)
                                || response.data.modelfile.match(/TEMPLATE\s+"([^"]+)"/);
                            if (templateMatch) {
                                this.sourceModelParams.template = templateMatch[1];
                            }
                        }
                        
                        // Parse MESSAGE instructions from modelfile
                        if (response.data.modelfile) {
                            const messages = this.parseMessagesFromModelfile(response.data.modelfile);
                            if (messages.length > 0) {
                                this.sourceModelParams.messages = messages;
                            }
                        }
                        
                        // Parse system prompt from modelfile
                        if (response.data.modelfile && !this.sourceModelParams.system) {
                            const systemMatch = response.data.modelfile.match(/SYSTEM\s+"""([\s\S]*?)"""/);
                            if (systemMatch) {
                                this.sourceModelParams.system = systemMatch[1];
                            }
                        }
                        
                        this.renderSourceParams(response.data);
                        $('#creator-source-name').text(modelName);
                        
                        // Auto-fill new model name if empty
                        if (!$('#creator-new-name').val()) {
                            $('#creator-new-name').val(modelName.split(':')[0] + '-custom');
                        }
                        
                        this.showNotification('success', 'Model Loaded', `Loaded parameters from ${modelName}`);
                    } else {
                        $sourceParams.html(`<div class="empty-state"><div style="color: #DC2626;">Error: ${this.escapeHtml(response.error)}</div></div>`);
                    }
                })
                .fail(() => {
                    $sourceParams.html('<div class="empty-state"><div style="color: #DC2626;">Failed to load model info</div></div>');
                });
        },
        
        // Parse stop sequences from modelfile content
        parseStopSequencesFromModelfile: function(modelfile) {
            const stopSequences = [];
            
            // Match PARAMETER stop "value" or just stop "value"
            const patterns = [
                /PARAMETER\s+stop\s+"([^"]*)"/g,
                /PARAMETER\s+stop\s+'([^']*)'/g,
                /PARAMETER\s+stop\s+([^\s\n]+)/g,
                /^stop\s+"([^"]*)"/gm,
                /^stop\s+'([^']*)'/gm,
                /^stop\s+([^\s\n]+)/gm
            ];
            
            patterns.forEach(pattern => {
                let match;
                while ((match = pattern.exec(modelfile)) !== null) {
                    let stopValue = match[1].trim();
                    // Handle escaped sequences
                    stopValue = stopValue.replace(/\\n/g, '\n').replace(/\\t/g, '\t');
                    // Handle Unicode escapes like \u003c
                    stopValue = stopValue.replace(/\\u([0-9a-fA-F]{4})/g, (_, hex) => 
                        String.fromCharCode(parseInt(hex, 16))
                    );
                    if (stopValue && !stopSequences.includes(stopValue)) {
                        stopSequences.push(stopValue);
                    }
                }
            });
            
            return stopSequences;
        },
        
        // Parse MESSAGE instructions from modelfile content
        parseMessagesFromModelfile: function(modelfile) {
            const messages = [];
            
            // Match MESSAGE role content
            const messagePattern = /MESSAGE\s+(user|assistant|system)\s+(.+?)(?=\nMESSAGE|\n[A-Z]+|$)/gs;
            let match;
            
            while ((match = messagePattern.exec(modelfile)) !== null) {
                const role = match[1].trim();
                let content = match[2].trim();
                // Remove surrounding quotes if present
                content = content.replace(/^["']|["']$/g, '');
                if (role && content) {
                    messages.push({ role, content });
                }
            }
            
            return messages;
        },
        
        renderSourceParams: function(data) {
            const $container = $('#creator-source-params');
            let html = '';
            
            // Model details section
            const details = data.details || {};
            html += `
                <div class="source-section" style="margin-bottom: 16px;">
                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Model Details</div>
                    <div class="source-param-item">
                        <span class="source-param-key">Family</span>
                        <span class="source-param-value">${this.escapeHtml(details.family || 'Unknown')}</span>
                    </div>
                    <div class="source-param-item">
                        <span class="source-param-key">Parameters</span>
                        <span class="source-param-value">${this.escapeHtml(details.parameter_size || 'Unknown')}</span>
                    </div>
                    <div class="source-param-item">
                        <span class="source-param-key">Quantization</span>
                        <span class="source-param-value">${this.escapeHtml(details.quantization_level || 'N/A')}</span>
                    </div>
                </div>
            `;
            
            // Parameters section
            const params = { ...data.parameters };
            
            // Add any additional parameters parsed from modelfile
            if (this.sourceModelParams) {
                Object.keys(this.sourceModelParams).forEach(key => {
                    if (!(key in params) && key !== 'messages' && key !== 'system' && key !== 'template') {
                        params[key] = this.sourceModelParams[key];
                    }
                });
            }
            
            // Filter out non-parameter keys for display
            const displayParams = Object.fromEntries(
                Object.entries(params).filter(([key]) => 
                    !['messages', 'system', 'template'].includes(key)
                )
            );
            
            if (Object.keys(displayParams).length > 0) {
                html += `<div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">Current Parameters</div>`;
                for (const [key, value] of Object.entries(displayParams)) {
                    if (key === 'stop') {
                        // Handle stop sequences specially
                        const stopSequences = Array.isArray(value) ? value : [value];
                        html += `
                            <div class="source-param-item source-param-clickable" data-param="${this.escapeHtml(key)}" onclick="App.copyParamToNew('stop')" title="Click to copy to New Model">
                                <span class="source-param-key">${this.escapeHtml(key)}</span>
                                <span class="source-param-value">${this.escapeHtml(stopSequences.map(s => '"' + s + '"').join(', '))}</span>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="source-param-item source-param-clickable" data-param="${this.escapeHtml(key)}" onclick="App.copyParamToNew('${this.escapeHtml(key)}')" title="Click to copy to New Model">
                                <span class="source-param-key">${this.escapeHtml(key)}</span>
                                <span class="source-param-value">${this.escapeHtml(String(value))}</span>
                            </div>
                        `;
                    }
                }
            } else {
                html += `<div style="font-size: 12px; color: var(--text-muted); padding: 12px; background: var(--bg-secondary); border-radius: 6px;">No custom parameters set. Using model defaults.</div>`;
            }
            
            // System prompt if exists
            const systemPrompt = this.sourceModelParams?.system || (data.modelfile?.match(/SYSTEM\s+"""([\s\S]*?)"""/)?.[1]);
            if (systemPrompt) {
                html += `
                    <div style="font-size: 11px; color: var(--text-muted); margin: 16px 0 8px;">System Prompt</div>
                    <div class="source-param-clickable" onclick="App.copySystemToNew()" title="Click to copy to New Model" style="font-size: 12px; background: var(--bg-secondary); padding: 10px; border-radius: 6px; font-family: var(--font-mono); white-space: pre-wrap; max-height: 100px; overflow-y: auto; cursor: pointer;">${this.escapeHtml(systemPrompt)}</div>
                `;
            }
            
            // Template if exists
            const templateText = this.sourceModelParams?.template || (data.modelfile?.match(/TEMPLATE\s+"""([\s\S]*?)"""/)?.[1]);
            if (templateText) {
                html += `
                    <div style="font-size: 11px; color: var(--text-muted); margin: 16px 0 8px;">Prompt Template</div>
                    <div class="source-param-clickable" onclick="App.copyTemplateToNew()" title="Click to copy to New Model" style="font-size: 12px; background: var(--bg-secondary); padding: 10px; border-radius: 6px; font-family: var(--font-mono); white-space: pre-wrap; max-height: 100px; overflow-y: auto; cursor: pointer;">${this.escapeHtml(templateText)}</div>
                `;
            }
            
            // Messages if exist
            const messages = this.sourceModelParams?.messages || [];
            if (messages.length > 0) {
                html += `
                    <div style="font-size: 11px; color: var(--text-muted); margin: 16px 0 8px;">Message Examples (${messages.length})</div>
                    <div class="source-param-clickable" onclick="App.copyMessagesToNew()" title="Click to copy to New Model" style="background: var(--bg-secondary); padding: 10px; border-radius: 6px; max-height: 150px; overflow-y: auto; cursor: pointer;">
                `;
                messages.forEach((msg, idx) => {
                    const roleColor = msg.role === 'user' ? '#3B82F6' : (msg.role === 'assistant' ? '#10B981' : '#8B5CF6');
                    html += `
                        <div style="margin-bottom: 8px; ${idx === messages.length - 1 ? '' : 'border-bottom: 1px solid var(--border-color); padding-bottom: 8px;'}">
                            <div style="font-size: 10px; color: ${roleColor}; font-weight: 600; text-transform: uppercase;">${this.escapeHtml(msg.role)}</div>
                            <div style="font-size: 12px; font-family: var(--font-mono); white-space: pre-wrap;">${this.escapeHtml(msg.content.substring(0, 100))}${msg.content.length > 100 ? '...' : ''}</div>
                        </div>
                    `;
                });
                html += `</div>`;
            }
            
            $container.html(html);
        },
        
        // Copy individual parameter to new model
        copyParamToNew: function(paramName) {
            if (!this.sourceModelParams || !this.sourceModelParams[paramName]) {
                this.showNotification('warning', 'No Value', 'No value to copy');
                return;
            }
            
            const value = this.sourceModelParams[paramName];
            
            if (paramName === 'stop') {
                this.setStopSequences(Array.isArray(value) ? value : [value]);
                this.showNotification('success', 'Copied', 'Stop sequences copied to New Model');
            } else {
                const $input = $(`#creator-${paramName}`);
                if ($input.length) {
                    $input.val(value);
                    this.updateParamDiff(paramName, value);
                    this.showNotification('success', 'Copied', `${paramName} copied to New Model`);
                }
            }
        },
        
        // Copy system prompt to new model
        copySystemToNew: function() {
            const systemPrompt = this.sourceModelParams?.system;
            if (systemPrompt) {
                $('#creator-system-prompt').val(systemPrompt);
                this.showNotification('success', 'Copied', 'System prompt copied to New Model');
            }
        },
        
        // Copy template to new model
        copyTemplateToNew: function() {
            const template = this.sourceModelParams?.template;
            if (template) {
                $('#creator-template').val(template);
                this.showNotification('success', 'Copied', 'Template copied to New Model');
            }
        },
        
        // Copy messages to new model
        copyMessagesToNew: function() {
            const messages = this.sourceModelParams?.messages;
            if (messages && messages.length > 0) {
                // Convert to user:/assistant: format
                const formatted = messages.map(m => `${m.role}: ${m.content}`).join('\n\n');
                $('#creator-message').val(formatted);
                this.showNotification('success', 'Copied', 'Message examples copied to New Model');
            }
        },
        
        applyParameterTemplate: function(templateName) {
            const template = this.parameterTemplates[templateName];
            if (!template) return;
            
            // Apply template values
            for (const [key, value] of Object.entries(template.params)) {
                const $input = $(`#creator-${key}`);
                if ($input.length) {
                    $input.val(value);
                    this.updateParamDiff(key, value);
                }
            }
            
            this.showNotification('success', 'Template Applied', `Applied "${template.name}" settings`);
        },
        
        copyAllSourceParams: function() {
            if (Object.keys(this.sourceModelParams).length === 0) {
                this.showNotification('warning', 'No Source', 'Load a source model first');
                return;
            }
            
            let copiedCount = 0;
            
            for (const [key, value] of Object.entries(this.sourceModelParams)) {
                // Skip non-parameter keys that have special handling
                if (['messages', 'system', 'template'].includes(key)) {
                    continue;
                }
                
                if (key === 'stop') {
                    // Handle stop sequences specially
                    this.setStopSequences(Array.isArray(value) ? value : [value]);
                    copiedCount++;
                } else {
                    const $input = $(`#creator-${key}`);
                    if ($input.length) {
                        $input.val(value);
                        this.updateParamDiff(key, value);
                        copiedCount++;
                    }
                }
            }
            
            // Copy system prompt if it exists
            if (this.sourceModelParams.system) {
                $('#creator-system-prompt').val(this.sourceModelParams.system);
                copiedCount++;
            }
            
            // Copy template if it exists
            if (this.sourceModelParams.template) {
                $('#creator-template').val(this.sourceModelParams.template);
                copiedCount++;
            }
            
            // Copy messages if they exist
            if (this.sourceModelParams.messages && this.sourceModelParams.messages.length > 0) {
                const formatted = this.sourceModelParams.messages.map(m => `${m.role}: ${m.content}`).join('\n\n');
                $('#creator-message').val(formatted);
                copiedCount++;
            }
            
            this.showNotification('success', 'Parameters Copied', `Copied ${copiedCount} settings from source model`);
        },
        
        resetCreatorToDefaults: function() {
            // Clear all input fields
            $('.creator-param input').val('').removeClass('changed new-value');
            $('.param-diff').removeClass('changed new').hide();
            $('#creator-system-prompt').val('');
            $('#creator-template').val('');
            $('#creator-message').val('');
            
            // Reset stop sequences to single empty input
            $('#creator-stop-sequences').html(`
                <div class="creator-stop-item">
                    <input type="text" class="aqua-input stop-input" placeholder="Enter stop sequence...">
                    <button class="aqua-btn tiny danger" onclick="App.removeStopSequence(this)">✕</button>
                </div>
            `);
            
            this.showNotification('info', 'Reset', 'Parameters reset to defaults');
        },
        
        updateParamDiff: function(paramName, value) {
            const $input = $(`#creator-${paramName}`);
            const $diff = $(`#diff-${paramName}`);
            const sourceValue = this.sourceModelParams[paramName];
            const defaultValue = this.defaultParams[paramName];
            
            // Remove existing classes
            $input.removeClass('changed new-value');
            $diff.removeClass('changed new').hide();
            
            if (value === '' || value === null || value === undefined) {
                return;
            }
            
            // Check if different from source
            if (sourceValue !== undefined && String(sourceValue) !== String(value)) {
                $input.addClass('changed');
                $diff.addClass('changed').text('Modified').show();
            } else if (sourceValue === undefined && String(value) !== String(defaultValue)) {
                $input.addClass('new-value');
                $diff.addClass('new').text('New').show();
            }
        },
        
        addStopSequence: function() {
            $('#creator-stop-sequences').append(`
                <div class="creator-stop-item">
                    <input type="text" class="aqua-input stop-input" placeholder="Enter stop sequence...">
                    <button class="aqua-btn tiny danger" onclick="App.removeStopSequence(this)">✕</button>
                </div>
            `);
        },
        
        removeStopSequence: function(btn) {
            const $items = $('#creator-stop-sequences .creator-stop-item');
            if ($items.length > 1) {
                $(btn).closest('.creator-stop-item').remove();
            } else {
                // Keep at least one, just clear it
                $(btn).siblings('.stop-input').val('');
            }
        },
        
        setStopSequences: function(sequences) {
            const $container = $('#creator-stop-sequences');
            $container.empty();
            
            if (!sequences || sequences.length === 0) {
                sequences = [''];
            }
            
            sequences.forEach(seq => {
                $container.append(`
                    <div class="creator-stop-item">
                        <input type="text" class="aqua-input stop-input" value="${this.escapeHtml(seq)}" placeholder="Enter stop sequence...">
                        <button class="aqua-btn tiny danger" onclick="App.removeStopSequence(this)">✕</button>
                    </div>
                `);
            });
        },
        
        toggleCreatorAdvanced: function() {
            const $content = $('#creator-advanced-content');
            const $toggle = $('#creator-advanced-toggle');
            
            if ($content.is(':visible')) {
                $content.slideUp(200);
                $toggle.text('▶');
            } else {
                $content.slideDown(200);
                $toggle.text('▼');
            }
        },
        
        getCreatorParams: function() {
            const params = {};
            
            // Core params
            const paramFields = [
                'temperature', 'num_ctx', 'num_predict', 'seed',
                'top_k', 'top_p', 'min_p', 'typical_p',
                'repeat_penalty', 'repeat_last_n', 'presence_penalty', 'frequency_penalty'
            ];
            
            paramFields.forEach(field => {
                const val = $(`#creator-${field}`).val();
                if (val !== '' && val !== null) {
                    params[field] = val;
                }
            });
            
            // Stop sequences
            const stopSequences = [];
            $('.stop-input').each(function() {
                const val = $(this).val().trim();
                if (val) {
                    stopSequences.push(val);
                }
            });
            if (stopSequences.length > 0) {
                params.stop = stopSequences;
            }
            
            return params;
        },
        
        // Parse message textarea content into message objects
        parseMessageExamples: function() {
            const messageText = $('#creator-message').val().trim();
            if (!messageText) return [];
            
            const messages = [];
            const lines = messageText.split('\n');
            let currentRole = null;
            let currentContent = [];
            
            for (const line of lines) {
                // Check for role prefixes: user:, assistant:, system:
                const roleMatch = line.match(/^(user|assistant|system):\s*/i);
                if (roleMatch) {
                    // Save previous message if exists
                    if (currentRole && currentContent.length > 0) {
                        messages.push({
                            role: currentRole,
                            content: currentContent.join('\n').trim()
                        });
                    }
                    // Start new message
                    currentRole = roleMatch[1].toLowerCase();
                    const remainder = line.substring(roleMatch[0].length);
                    currentContent = remainder ? [remainder] : [];
                } else if (currentRole) {
                    // Continue current message
                    currentContent.push(line);
                }
            }
            
            // Don't forget the last message
            if (currentRole && currentContent.length > 0) {
                messages.push({
                    role: currentRole,
                    content: currentContent.join('\n').trim()
                });
            }
            
            return messages;
        },
        
        // Generate Modelfile-style preview
        generateModelfilePreview: function(payload) {
            let modelfile = `# Modelfile for ${payload.name}\n`;
            modelfile += `# Generated by Ollama Manager\n\n`;
            modelfile += `FROM ${payload.from}\n\n`;
            
            if (payload.system) {
                modelfile += `SYSTEM """${payload.system}"""\n\n`;
            }
            
            if (payload.template) {
                modelfile += `TEMPLATE """${payload.template}"""\n\n`;
            }
            
            if (payload.parameters) {
                for (const [key, value] of Object.entries(payload.parameters)) {
                    if (key === 'stop' && Array.isArray(value)) {
                        value.forEach(stopVal => {
                            modelfile += `PARAMETER stop "${stopVal}"\n`;
                        });
                    } else {
                        modelfile += `PARAMETER ${key} ${value}\n`;
                    }
                }
                modelfile += '\n';
            }
            
            if (payload.messages && payload.messages.length > 0) {
                payload.messages.forEach(msg => {
                    // Escape content if needed
                    const content = msg.content.replace(/"/g, '\\"');
                    modelfile += `MESSAGE ${msg.role} "${content}"\n`;
                });
            }
            
            return modelfile;
        },
        
        validateCreatorForm: function() {
            const errors = [];
            const newName = $('#creator-new-name').val().trim();
            const sourceModel = $('#creator-source-model').val();
            
            if (!newName) {
                errors.push('New model name is required');
            } else if (!/^[a-zA-Z0-9][a-zA-Z0-9_\-.:\/]*$/.test(newName)) {
                errors.push('Model name can only contain letters, numbers, hyphens, underscores, and colons');
            }
            
            if (!sourceModel) {
                errors.push('Source model is required');
            }
            
            // Validate parameter ranges
            const temp = parseFloat($('#creator-temperature').val());
            if (!isNaN(temp) && (temp < 0 || temp > 2)) {
                errors.push('Temperature must be between 0 and 2');
            }
            
            const topP = parseFloat($('#creator-top_p').val());
            if (!isNaN(topP) && (topP < 0 || topP > 1)) {
                errors.push('top_p must be between 0 and 1');
            }
            
            const minP = parseFloat($('#creator-min_p').val());
            if (!isNaN(minP) && (minP < 0 || minP > 1)) {
                errors.push('min_p must be between 0 and 1');
            }
            
            return errors;
        },
        
        showValidationErrors: function(errors) {
            const $validation = $('#creator-validation');
            
            if (errors.length === 0) {
                $validation.hide();
                return;
            }
            
            const html = errors.map(e => `
                <div class="creator-validation-item">
                    <span>⚠️</span>
                    <span>${this.escapeHtml(e)}</span>
                </div>
            `).join('');
            
            $validation.removeClass('success').html(html).show();
        },
        
        previewModelCreation: function() {
            const errors = this.validateCreatorForm();
            if (errors.length > 0) {
                this.showValidationErrors(errors);
                return;
            }
            
            const newName = $('#creator-new-name').val().trim();
            const sourceModel = $('#creator-source-model').val();
            const params = this.getCreatorParams();
            const systemPrompt = $('#creator-system-prompt').val().trim();
            const template = $('#creator-template').val().trim();
            const messages = this.parseMessageExamples();
            
            const payload = {
                name: newName,
                from: sourceModel,
                parameters: params
            };
            if (systemPrompt) payload.system = systemPrompt;
            if (template) payload.template = template;
            if (messages.length > 0) payload.messages = messages;
            
            // Generate Modelfile preview
            const modelfilePreview = this.generateModelfilePreview(payload);
            
            // Show preview modal (styled like a window)
            const $overlay = $(`
                <div class="creator-preview-overlay" onclick="if(event.target===this) $(this).remove();" style="z-index: 100000;">
                    <div class="window creator-preview-modal visible" id="window-preview-modal" style="position: relative; width: 700px; height: 600px; z-index: 100001;">
                        <div class="window-titlebar" style="cursor: move;">
                            <div class="traffic-lights">
                                <div class="traffic-light close" onclick="$(this).closest('.creator-preview-overlay').remove()"></div>
                                <div class="traffic-light minimize" onclick="$(this).closest('.window').toggleClass('minimized')"></div>
                                <div class="traffic-light maximize" onclick="App.maximizePreviewWindow()"></div>
                            </div>
                            <div class="window-title">📋 Model Creation Preview</div>
                        </div>
                        <div class="window-toolbar" style="padding: 8px 16px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
                            <button class="aqua-btn small" id="preview-tab-json" onclick="App.switchPreviewTab('json')" style="margin-right: 8px;">📊 API Payload</button>
                            <button class="aqua-btn small" id="preview-tab-modelfile" onclick="App.switchPreviewTab('modelfile')">📄 Modelfile Format</button>
                        </div>
                        <div class="window-content" style="padding: 16px; height: calc(100% - 110px); overflow-y: auto;">
                            <div id="preview-content-json">
                                <p style="margin-bottom: 12px; font-size: 13px; color: var(--text-muted);">The following JSON payload will be sent to the Ollama API:</p>
                                <pre style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; max-height: 400px; white-space: pre-wrap; word-wrap: break-word;">${this.escapeHtml(JSON.stringify(payload, null, 2) || 'No data to preview')}</pre>
                            </div>
                            <div id="preview-content-modelfile" style="display: none;">
                                <p style="margin-bottom: 12px; font-size: 13px; color: var(--text-muted);">Equivalent Modelfile format that will be generated:</p>
                                <pre style="background: var(--bg-secondary); padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; max-height: 400px; white-space: pre-wrap; word-wrap: break-word;">${this.escapeHtml(modelfilePreview)}</pre>
                            </div>
                        </div>
                        <div class="window-footer" style="display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px;">
                            <button class="aqua-btn" onclick="$(this).closest('.creator-preview-overlay').remove()">Cancel</button>
                            <button class="aqua-btn primary" onclick="$(this).closest('.creator-preview-overlay').remove(); App.createNewModel();">🚀 Create Model</button>
                        </div>
                        
                        <!-- Resize handles -->
                        <div class="window-resize-handle window-resize-n"></div>
                        <div class="window-resize-handle window-resize-s"></div>
                        <div class="window-resize-handle window-resize-e"></div>
                        <div class="window-resize-handle window-resize-w"></div>
                        <div class="window-resize-handle window-resize-nw"></div>
                        <div class="window-resize-handle window-resize-ne"></div>
                        <div class="window-resize-handle window-resize-sw"></div>
                        <div class="window-resize-handle window-resize-se"></div>
                    </div>
                </div>
            `);
            
            $('body').append($overlay);
            
            // Make the preview modal resizable (but not draggable since it's a lightbox)
            this.makeWindowResizable($overlay.find('.window'));
            this.focusWindow('window-preview-modal');
            
            // Set initial tab state
            $('#preview-tab-json').addClass('primary');
        },
        
        // Switch between preview tabs
        switchPreviewTab: function(tab) {
            if (tab === 'json') {
                $('#preview-content-json').show();
                $('#preview-content-modelfile').hide();
                $('#preview-tab-json').addClass('primary');
                $('#preview-tab-modelfile').removeClass('primary');
            } else {
                $('#preview-content-json').hide();
                $('#preview-content-modelfile').show();
                $('#preview-tab-json').removeClass('primary');
                $('#preview-tab-modelfile').addClass('primary');
            }
        },
        
        // Maximize preview window
        maximizePreviewWindow: function() {
            const $modal = $('.creator-preview-modal');
            const $overlay = $('.creator-preview-overlay');
            
            if ($modal.hasClass('maximized')) {
                $modal.removeClass('maximized').css({
                    width: $modal.data('original-width'),
                    height: $modal.data('original-height')
                });
            } else {
                $modal.data('original-width', $modal.css('width'));
                $modal.data('original-height', $modal.css('height'));
                $modal.addClass('maximized').css({
                    width: '90vw',
                    height: '90vh'
                });
            }
        },
        
        createNewModel: function() {
            const errors = this.validateCreatorForm();
            if (errors.length > 0) {
                this.showValidationErrors(errors);
                return;
            }
            
            const newName = $('#creator-new-name').val().trim();
            const sourceModel = $('#creator-source-model').val();
            const params = this.getCreatorParams();
            const systemPrompt = $('#creator-system-prompt').val().trim();
            const template = $('#creator-template').val().trim();
            const messages = this.parseMessageExamples();
            
            const $btn = $('#creator-create-btn');
            const $status = $('#creator-status');
            
            $btn.addClass('loading').prop('disabled', true).text('🔄 Creating');
            $status.text(`Creating ${newName} from ${sourceModel}...`);
            $('#creator-validation').hide();
            
            const payload = {
                name: newName,
                from: sourceModel,
                parameters: params
            };
            if (systemPrompt) payload.system = systemPrompt;
            if (template) payload.template = template;
            if (messages.length > 0) payload.messages = messages;
            
            $.ajax({
                url: this.api.models + '?action=create_advanced',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                timeout: 300000 // 5 minute timeout
            })
            .done((response) => {
                if (response.success) {
                    this.showModelCreationSuccess(newName);
                    this.loadModels(); // Refresh model list
                    $status.text(`Successfully created ${newName}`);
                } else {
                    $status.text(`Error: ${response.error}`);
                    this.showNotification('error', 'Creation Failed', response.error);
                    $('#creator-validation')
                        .removeClass('success')
                        .html(`<div class="creator-validation-item"><span>❌</span><span>${this.escapeHtml(response.error)}</span></div>`)
                        .show();
                }
            })
            .fail((xhr) => {
                const errorMsg = xhr.responseJSON?.error || 'Request failed';
                $status.text(`Error: ${errorMsg}`);
                this.showNotification('error', 'Creation Failed', errorMsg);
            })
            .always(() => {
                $btn.removeClass('loading').prop('disabled', false).text('🚀 Create Model');
            });
        },
        
        showModelCreationSuccess: function(modelName) {
            const $window = $('#window-modelcreator');
            const $overlay = $(`
                <div class="creator-success-overlay">
                    <div class="creator-success-icon">✅</div>
                    <div class="creator-success-text">Model Created!</div>
                    <div class="creator-success-subtext">${this.escapeHtml(modelName)} is ready to use</div>
                    <div style="margin-top: 24px; display: flex; gap: 12px;">
                        <button class="aqua-btn" onclick="$(this).closest('.creator-success-overlay').fadeOut(300, function(){ $(this).remove(); })">Close</button>
                        <button class="aqua-btn primary" onclick="App.openChatWithModel('${this.escapeHtml(modelName)}'); $(this).closest('.creator-success-overlay').remove();">💬 Chat with Model</button>
                    </div>
                </div>
            `);
            
            $window.find('.window-content').css('position', 'relative').append($overlay);
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                $overlay.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
            
            this.showNotification('success', 'Model Created', `${modelName} created successfully!`);
        },
        
        exportCreatorParams: function() {
            const params = this.getCreatorParams();
            const systemPrompt = $('#creator-system-prompt').val().trim();
            const template = $('#creator-template').val().trim();
            const messageText = $('#creator-message').val().trim();
            const messages = this.parseMessageExamples();
            
            const config = {
                parameters: params,
                system: systemPrompt || undefined,
                template: template || undefined,
                messages: messages.length > 0 ? messages : undefined,
                messageText: messageText || undefined, // Raw text for re-import
                exportedAt: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(config, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'model-config.json';
            a.click();
            URL.revokeObjectURL(url);
            
            this.showNotification('success', 'Exported', 'Configuration exported successfully');
        },
        
        importCreatorParams: function() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const config = JSON.parse(e.target.result);
                        
                        // Apply parameters
                        if (config.parameters) {
                            for (const [key, value] of Object.entries(config.parameters)) {
                                if (key === 'stop') {
                                    this.setStopSequences(Array.isArray(value) ? value : [value]);
                                } else {
                                    const $input = $(`#creator-${key}`);
                                    if ($input.length) {
                                        $input.val(value);
                                        this.updateParamDiff(key, value);
                                    }
                                }
                            }
                        }
                        
                        // Apply system prompt
                        if (config.system) {
                            $('#creator-system-prompt').val(config.system);
                        }
                        
                        // Apply template
                        if (config.template) {
                            $('#creator-template').val(config.template);
                        }
                        
                        // Apply message examples - prefer raw text if available, otherwise format from messages array
                        if (config.messageText) {
                            $('#creator-message').val(config.messageText);
                        } else if (config.messages && Array.isArray(config.messages)) {
                            const formatted = config.messages.map(m => `${m.role}: ${m.content}`).join('\n\n');
                            $('#creator-message').val(formatted);
                        } else if (config.message) {
                            // Backwards compatibility
                            $('#creator-message').val(config.message);
                        }
                        
                        this.showNotification('success', 'Imported', 'Configuration imported successfully');
                    } catch (err) {
                        this.showNotification('error', 'Import Failed', 'Invalid JSON file');
                    }
                };
                reader.readAsText(file);
            };
            
            input.click();
        },

        // ============================================
        // CHAT INTERFACE
        // ============================================
        openChatWithModel: function(modelName) {
            this.state.chatModel = modelName;
            $('#chat-model-select').val(modelName);
            this.showWindow('chat');
        },

        createNewChat: function() {
            this.clearChat();
            this.state.currentChatId = null;
            this.showNotification('info', 'New Chat', 'Started a new conversation');
        },

        toggleChatOptions: function() {
            const $panel = $('#chat-options-panel');
            const isVisible = $panel.is(':visible');
            $panel.slideToggle(200);
            $('#chat-options-toggle').toggleClass('active', !isVisible);
        },

        setChatTemp: function(value) {
            $('#chat-temperature').val(value);
            $('#chat-temp-value').text(value);
        },

        loadSystemPrompts: function() {
            $.get(this.api.prompts + '?action=list')
                .done((response) => {
                    if (response.success) {
                        this.state.systemPrompts = response.data.prompts || [];
                        this.renderSystemPromptSelector();
                    }
                });
        },

        renderSystemPromptSelector: function() {
            const $select = $('#chat-preset-select');
            $select.html('<option value="">None</option>');
            
            const grouped = {};
            this.state.systemPrompts.forEach(p => {
                if (!grouped[p.category]) grouped[p.category] = [];
                grouped[p.category].push(p);
            });
            
            Object.keys(grouped).sort().forEach(category => {
                const $group = $(`<optgroup label="${this.escapeHtml(category.charAt(0).toUpperCase() + category.slice(1))}">`);
                grouped[category].forEach(p => {
                    $group.append(`<option value="${p.id}" data-content="${this.escapeHtml(p.content)}">${p.icon} ${this.escapeHtml(p.name)}</option>`);
                });
                $select.append($group);
            });
        },

        loadSchemaPreset: function(name) {
            if (name === 'custom') {
                $('#chat-json-schema').val('');
                return;
            }
            const schema = this.schemaPresets[name];
            if (schema) {
                $('#chat-json-schema').val(JSON.stringify(schema, null, 2));
            }
        },

        saveSystemPrompt: function() {
            const content = $('#chat-system-prompt').val().trim();
            if (!content) {
                this.showNotification('warning', 'Empty Prompt', 'Enter a system prompt first');
                return;
            }
            
            const name = prompt('Enter a name for this prompt:');
            if (!name) return;
            
            $.ajax({
                url: this.api.prompts + '?action=create',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    name: name,
                    content: content,
                    category: 'custom',
                    icon: '📝'
                })
            })
            .done((response) => {
                if (response.success) {
                    this.showNotification('success', 'Saved', 'System prompt saved successfully');
                    this.loadSystemPrompts();
                }
            });
        },

        sendChatMessage: function() {
            const $input = $('#chat-input');
            const message = $input.val().trim();
            const model = $('#chat-model-select').val();
            
            if (!message && this.state.chatImages.length === 0) return;
            if (!model) {
                this.showNotification('warning', 'Select Model', 'Please select a model first');
                return;
            }
            
            if (this.state.isGenerating) {
                this.showNotification('warning', 'Please Wait', 'Still generating previous response');
                return;
            }
            
            // Clear welcome screen if first message and hide it
            if (this.state.chatMessages.length === 0) {
                $('#chat-messages').empty();
                // Remove the welcome screen class content
                $('.chat-welcome').remove();
            }
            
            // Build user message with optional images
            const userMessage = {
                role: 'user',
                content: message
            };
            
            // Add images for vision models
            if (this.state.chatImages.length > 0) {
                userMessage.images = this.state.chatImages.map(img => img.base64);
            }
            
            // Add user message to UI
            this.addChatMessage('user', message, this.state.chatImages);
            $input.val('').focus();
            
            // Clear image preview
            this.clearImagePreview();
            
            // Add to messages array
            this.state.chatMessages.push(userMessage);
            
            // Update message count
            this.updateChatStats();
            
            // Check if streaming is enabled
            const streamingEnabled = $('#chat-streaming-enabled').is(':checked');
            
            if (streamingEnabled) {
                this.sendStreamingMessage(model);
            } else {
                this.sendNonStreamingMessage(model);
            }
        },

        sendStreamingMessage: function(model) {
            const self = this;
            this.state.isGenerating = true;
            
            // Reset token tracking
            this.state.streamingTokenCount = 0;
            this.state.streamingFirstTokenTime = null;
            this.state.streamingCharCount = 0;
            
            // Show typing indicator
            $('#chat-typing').show();
            $('#chat-typing .typing-text').text(`${model} is thinking...`);
            this.scrollChatToBottom();
            
            // Build request payload
            const payload = this.buildChatPayload(model);
            
            // Create placeholder for streaming response
            const $placeholder = $('<div class="chat-message assistant"><div class="chat-avatar">🤖</div><div class="chat-bubble streaming-bubble"></div></div>');
            $('#chat-messages').append($placeholder);
            
            let fullContent = '';
            const startTime = Date.now();
            
            // Create AbortController for cancellation
            const abortController = new AbortController();
            this.state.abortController = abortController;
            
            // Use fetch with streaming for SSE
            fetch(this.api.chatStream, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: abortController.signal
            })
            .then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                function processStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            self.finishStreaming($placeholder, fullContent, startTime);
                            return;
                        }
                        
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop(); // Keep incomplete line in buffer
                        
                        lines.forEach(line => {
                            if (line.startsWith('data: ')) {
                                try {
                                    const data = JSON.parse(line.slice(6));
                                    
                                    if (data.content) {
                                        // Track first token time
                                        if (self.state.streamingFirstTokenTime === null) {
                                            self.state.streamingFirstTokenTime = Date.now();
                                        }
                                        
                                        fullContent += data.content;
                                        
                                        // Update token tracking (estimate: ~4 chars per token)
                                        self.state.streamingCharCount += data.content.length;
                                        self.state.streamingTokenCount = Math.ceil(self.state.streamingCharCount / 4);
                                        
                                        // Update live stats
                                        self.updateStreamingStats();
                                        
                                        $placeholder.find('.streaming-bubble').html(self.formatMessageContent(fullContent));
                                        self.scrollChatToBottom();
                                    }
                                    
                                    if (data.error) {
                                        $placeholder.find('.streaming-bubble').html(`<span style="color: #EF4444;">Error: ${self.escapeHtml(data.error)}</span>`);
                                    }
                                    
                                    if (data.tool_calls) {
                                        self.handleToolCalls(data.tool_calls, $placeholder);
                                    }
                                } catch (e) {
                                    // Ignore parse errors
                                }
                            }
                        });
                        
                        return processStream();
                    });
                }
                
                return processStream();
            })
            .catch(error => {
                // Don't show error if it was aborted
                if (error.name === 'AbortError') {
                    self.finishStreaming($placeholder, fullContent, startTime);
                    return;
                }
                
                // Clear abort controller on error
                self.state.abortController = null;
                
                console.error('Streaming error:', error);
                $placeholder.find('.streaming-bubble').html(`<span style="color: #EF4444;">Error: Connection failed</span>`);
                self.state.isGenerating = false;
                $('#chat-typing').hide();
            });
        },
        
        // Update streaming stats in real-time
        updateStreamingStats: function() {
            if (this.state.streamingFirstTokenTime === null) return;
            
            const elapsedSec = (Date.now() - this.state.streamingFirstTokenTime) / 1000;
            if (elapsedSec > 0) {
                const tokensPerSec = (this.state.streamingTokenCount / elapsedSec).toFixed(1);
                $('#chat-tokens-per-sec')
                    .text(`Speed: ${tokensPerSec} tok/s`)
                    .removeClass('speed-fast speed-good speed-medium speed-slow')
                    .addClass(this.getSpeedClass(parseFloat(tokensPerSec)));
                $('#chat-token-count').text(`Tokens: ~${this.state.streamingTokenCount}`);
                $('#chat-response-time').text(`Time: ${elapsedSec.toFixed(1)}s`);
            }
        },
        
        // Get CSS class based on tokens/sec performance
        getSpeedClass: function(tokensPerSec) {
            if (tokensPerSec >= 30) return 'speed-fast';
            if (tokensPerSec >= 15) return 'speed-good';
            if (tokensPerSec >= 5) return 'speed-medium';
            return 'speed-slow';
        },

        finishStreaming: function($placeholder, content, startTime) {
            this.state.isGenerating = false;
            $('#chat-typing').hide();
            
            // Clear abort controller
            this.state.abortController = null;
            
            // Remove streaming class
            $placeholder.find('.streaming-bubble').removeClass('streaming-bubble');
            
            // Add message actions
            this.addMessageActions($placeholder);
            
            // Add to messages array
            this.state.chatMessages.push({
                role: 'assistant',
                content: content
            });
            
            // Update final stats with token performance
            const totalDuration = Date.now() - startTime;
            const streamingDuration = this.state.streamingFirstTokenTime 
                ? (Date.now() - this.state.streamingFirstTokenTime) / 1000 
                : totalDuration / 1000;
            
            // Calculate final tokens/sec (from first token)
            if (this.state.streamingTokenCount > 0 && streamingDuration > 0) {
                const finalTokPerSec = (this.state.streamingTokenCount / streamingDuration).toFixed(1);
                $('#chat-tokens-per-sec')
                    .text(`Speed: ${finalTokPerSec} tok/s`)
                    .removeClass('speed-fast speed-good speed-medium speed-slow')
                    .addClass(this.getSpeedClass(parseFloat(finalTokPerSec)));
                $('#chat-token-count').text(`Tokens: ~${this.state.streamingTokenCount}`);
            }
            
            $('#chat-response-time').text(`Time: ${(totalDuration / 1000).toFixed(1)}s`);
            this.updateChatStats();
            
            // Apply syntax highlighting
            $placeholder.find('pre code').each(function() {
                if (window.hljs) {
                    hljs.highlightElement(this);
                }
            });
            
            // Add copy buttons to code blocks
            this.addCodeCopyButtons($placeholder);
            
            // Generate smart suggestions
            this.generateSuggestions();
            
            // Refresh chat history list
            this.loadChatHistory();
        },

        sendNonStreamingMessage: function(model) {
            const self = this;
            this.state.isGenerating = true;
            
            // Reset token tracking
            $('#chat-tokens-per-sec').text('Speed: --').removeClass('speed-fast speed-good speed-medium speed-slow');
            
            // Show typing indicator
            $('#chat-typing').show();
            $('#chat-typing .typing-text').text(`${model} is thinking...`);
            
            // Show loading
            const $loading = $('<div class="chat-message assistant"><div class="chat-avatar">🤖</div><div class="chat-bubble"><div class="loading-spinner"></div></div></div>');
            $('#chat-messages').append($loading);
            this.scrollChatToBottom();
            
            const payload = this.buildChatPayload(model);
            const startTime = Date.now();
            
            $.ajax({
                url: this.api.chat,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload)
            })
            .done((response) => {
                $loading.remove();
                
                if (response.success) {
                    const assistantMessage = response.data.message.content;
                    this.addChatMessage('assistant', assistantMessage);
                    this.state.chatMessages.push({
                        role: 'assistant',
                        content: assistantMessage
                    });
                    
                    // Update stats with tokens/sec calculation
                    const duration = Date.now() - startTime;
                    const durationSec = duration / 1000;
                    $('#chat-response-time').text(`Time: ${durationSec.toFixed(1)}s`);
                    
                    // Use eval_count from API response for accurate token count
                    if (response.data.eval_count) {
                        const tokenCount = response.data.eval_count;
                        const tokensPerSec = (tokenCount / durationSec).toFixed(1);
                        $('#chat-token-count').text(`Tokens: ${tokenCount}`);
                        $('#chat-tokens-per-sec')
                            .text(`Speed: ${tokensPerSec} tok/s`)
                            .removeClass('speed-fast speed-good speed-medium speed-slow')
                            .addClass(this.getSpeedClass(parseFloat(tokensPerSec)));
                    }
                    
                    // Generate smart suggestions
                    this.generateSuggestions();
                    
                    // Refresh chat history list
                    this.loadChatHistory();
                } else {
                    this.addChatMessage('assistant', 'Error: ' + response.error);
                }
            })
            .fail(() => {
                $loading.remove();
                this.addChatMessage('assistant', 'Error: Failed to get response');
            })
            .always(() => {
                this.state.isGenerating = false;
                $('#chat-typing').hide();
                this.updateChatStats();
            });
        },

        buildChatPayload: function(model) {
            const payload = {
                model: model,
                messages: [...this.state.chatMessages],
                options: {}
            };
            
            // System prompt
            const systemPrompt = $('#chat-system-prompt').val().trim();
            if (systemPrompt) {
                payload.system = systemPrompt;
            }
            
            // Preset system prompt
            const presetId = $('#chat-preset-select').val();
            if (presetId && !systemPrompt) {
                const preset = this.state.systemPrompts.find(p => p.id == presetId);
                if (preset) {
                    payload.system = preset.content;
                }
            }
            
            // Temperature
            const temp = parseFloat($('#chat-temperature').val());
            if (!isNaN(temp)) {
                payload.options.temperature = temp;
            }
            
            // Max tokens
            const maxTokens = parseInt($('#chat-max-tokens').val());
            if (!isNaN(maxTokens) && maxTokens > 0) {
                payload.options.num_predict = maxTokens;
            }
            
            // Context size
            const ctxSize = parseInt($('#chat-context-size').val());
            if (!isNaN(ctxSize) && ctxSize > 0) {
                payload.options.num_ctx = ctxSize;
            }
            
            // Seed
            const seed = parseInt($('#chat-seed').val());
            if (!isNaN(seed)) {
                payload.options.seed = seed;
            }
            
            // Top P
            const topP = parseFloat($('#chat-top-p').val());
            if (!isNaN(topP)) {
                payload.options.top_p = topP;
            }
            
            // Top K
            const topK = parseInt($('#chat-top-k').val());
            if (!isNaN(topK)) {
                payload.options.top_k = topK;
            }
            
            // Structured output
            if ($('#chat-structured-output').is(':checked')) {
                try {
                    const schema = JSON.parse($('#chat-json-schema').val());
                    payload.format = schema;
                } catch (e) {
                    // Invalid schema, ignore
                }
            }
            
            return payload;
        },

        stopGeneration: function() {
            if (this.state.chatEventSource) {
                this.state.chatEventSource.close();
                this.state.chatEventSource = null;
            }
            
            if (this.state.abortController) {
                this.state.abortController.abort();
                this.state.abortController = null;
            }
            
            this.state.isGenerating = false;
            $('#chat-typing').hide();
            this.showNotification('info', 'Stopped', 'Generation stopped');
        },

        handleToolCalls: function(toolCalls, $placeholder) {
            // Display tool call information
            toolCalls.forEach(call => {
                const $toolCall = $(`
                    <div class="chat-tool-call">
                        <div class="chat-tool-call-header">
                            🔧 Tool Call: ${this.escapeHtml(call.function.name)}
                        </div>
                        <div class="chat-tool-call-body">
                            Arguments: ${this.escapeHtml(JSON.stringify(call.function.arguments))}
                        </div>
                    </div>
                `);
                $placeholder.find('.chat-bubble').append($toolCall);
            });
        },

        addChatMessage: function(role, content, images = []) {
            const avatar = role === 'user' ? '👤' : (role === 'system' ? '⚙️' : '🤖');
            const formattedContent = this.formatMessageContent(content);
            const timestamp = new Date().toLocaleTimeString();
            
            let imagesHtml = '';
            if (images && images.length > 0) {
                imagesHtml = '<div class="chat-message-images">';
                images.forEach(img => {
                    imagesHtml += `<img src="data:${img.mimeType};base64,${img.base64}" class="chat-message-image" onclick="App.viewImage(this)">`;
                });
                imagesHtml += '</div>';
            }
            
            const $message = $(`
                <div class="chat-message ${role}">
                    <div class="chat-avatar">${avatar}</div>
                    <div class="chat-bubble">
                        ${imagesHtml}
                        ${formattedContent}
                        <div class="chat-message-time">${timestamp}</div>
                    </div>
                    <div class="chat-message-actions">
                        <button class="chat-message-action" onclick="App.copyMessage(this)" title="Copy">📋</button>
                        ${role === 'assistant' ? '<button class="chat-message-action" onclick="App.regenerateMessage(this)" title="Regenerate">🔄</button>' : ''}
                        ${role === 'assistant' ? '<button class="chat-message-action" onclick="App.rateMessage(this, 1)" title="Good">👍</button>' : ''}
                        ${role === 'assistant' ? '<button class="chat-message-action" onclick="App.rateMessage(this, -1)" title="Bad">👎</button>' : ''}
                    </div>
                </div>
            `);
            
            $('#chat-messages').append($message);
            this.scrollChatToBottom();
            
            // Apply syntax highlighting
            $message.find('pre code').each(function() {
                if (window.hljs) {
                    hljs.highlightElement(this);
                }
            });
            
            // Add copy buttons to code blocks
            this.addCodeCopyButtons($message);
        },

        addMessageActions: function($message) {
            const $actions = $(`
                <div class="chat-message-actions">
                    <button class="chat-message-action" onclick="App.copyMessage(this)" title="Copy">📋</button>
                    <button class="chat-message-action" onclick="App.regenerateMessage(this)" title="Regenerate">🔄</button>
                    <button class="chat-message-action" onclick="App.rateMessage(this, 1)" title="Good">👍</button>
                    <button class="chat-message-action" onclick="App.rateMessage(this, -1)" title="Bad">👎</button>
                </div>
            `);
            $message.append($actions);
        },

        addCodeCopyButtons: function($container) {
            $container.find('pre').each(function() {
                if ($(this).find('.code-copy-btn').length === 0) {
                    $(this).css('position', 'relative');
                    $(this).append('<button class="code-copy-btn" onclick="App.copyCodeBlock(this)">Copy</button>');
                }
            });
        },

        copyMessage: function(btn) {
            const $bubble = $(btn).closest('.chat-message').find('.chat-bubble');
            const text = $bubble.text();
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('success', 'Copied', 'Message copied to clipboard');
            });
        },

        copyCodeBlock: function(btn) {
            const $pre = $(btn).closest('pre');
            const code = $pre.find('code').text() || $pre.text();
            navigator.clipboard.writeText(code).then(() => {
                $(btn).text('Copied!');
                setTimeout(() => $(btn).text('Copy'), 2000);
            });
        },

        regenerateMessage: function(btn) {
            // Remove last assistant message
            if (this.state.chatMessages.length > 0 && 
                this.state.chatMessages[this.state.chatMessages.length - 1].role === 'assistant') {
                this.state.chatMessages.pop();
                $(btn).closest('.chat-message').remove();
                
                // Resend the last user message
                const model = $('#chat-model-select').val();
                const streamingEnabled = $('#chat-streaming-enabled').is(':checked');
                
                if (streamingEnabled) {
                    this.sendStreamingMessage(model);
                } else {
                    this.sendNonStreamingMessage(model);
                }
            }
        },

        rateMessage: function(btn, rating) {
            $(btn).addClass('active').siblings('.chat-rating-btn').removeClass('active');
            // Could save rating to database here
            this.showNotification('success', 'Rated', rating > 0 ? 'Thanks for the feedback!' : 'Sorry about that!');
        },

        formatMessageContent: function(content) {
            if (!content) return '';
            
            // Use marked.js for markdown parsing if available
            if (window.marked) {
                // Configure marked
                marked.setOptions({
                    breaks: true,
                    gfm: true,
                    highlight: function(code, lang) {
                        if (window.hljs && lang && hljs.getLanguage(lang)) {
                            try {
                                return hljs.highlight(code, { language: lang }).value;
                            } catch (e) {}
                        }
                        return code;
                    }
                });
                
                try {
                    return marked.parse(content);
                } catch (e) {
                    console.warn('Markdown parsing failed:', e);
                }
            }
            
            // Fallback to simple formatting
            let formatted = this.escapeHtml(content);
            formatted = formatted.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
            formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');
            formatted = formatted.replace(/\n/g, '<br>');
            
            return formatted;
        },

        scrollChatToBottom: function() {
            const $messages = $('#chat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        updateChatStats: function() {
            $('#chat-message-count').text(`Messages: ${this.state.chatMessages.length}`);
        },

        clearChat: function() {
            this.state.chatMessages = [];
            this.state.currentChatId = null;
            this.clearImagePreview();
            $('#chat-messages').html(`
                <div class="chat-welcome">
                    <div class="chat-welcome-icon">🦙</div>
                    <h2>Welcome to Advanced Chat</h2>
                    <p>Select a model and start a conversation. Features include:</p>
                    <div class="chat-features-grid">
                        <div class="chat-feature">⚡ Real-time streaming</div>
                        <div class="chat-feature">🖼️ Image analysis</div>
                        <div class="chat-feature">📝 System prompts</div>
                        <div class="chat-feature">🔧 Tool calling</div>
                        <div class="chat-feature">📋 Structured output</div>
                        <div class="chat-feature">💾 Chat history</div>
                    </div>
                </div>
            `);
            $('#chat-token-count').text('Tokens: --');
            $('#chat-response-time').text('Response: --');
            $('#chat-message-count').text('Messages: 0');
        },

        exportChat: function() {
            if (this.state.chatMessages.length === 0) {
                this.showNotification('warning', 'Empty Chat', 'No messages to export');
                return;
            }
            
            const format = prompt('Export format (json/markdown):', 'markdown');
            if (!format) return;
            
            let content, filename, type;
            
            if (format.toLowerCase() === 'json') {
                content = JSON.stringify({
                    model: $('#chat-model-select').val(),
                    messages: this.state.chatMessages,
                    exportedAt: new Date().toISOString()
                }, null, 2);
                filename = 'chat_export.json';
                type = 'application/json';
            } else {
                content = `# Chat Export\n\n**Model:** ${$('#chat-model-select').val()}\n**Date:** ${new Date().toLocaleString()}\n\n---\n\n`;
                this.state.chatMessages.forEach(msg => {
                    const role = msg.role.charAt(0).toUpperCase() + msg.role.slice(1);
                    content += `### ${role}\n\n${msg.content}\n\n---\n\n`;
                });
                filename = 'chat_export.md';
                type = 'text/markdown';
            }
            
            const blob = new Blob([content], { type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
            
            this.showNotification('success', 'Exported', `Chat exported as ${filename}`);
        },

        // Image Upload Functions
        triggerImageUpload: function() {
            $('#chat-image-input').click();
        },

        handleImageUpload: function(files) {
            const self = this;
            
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) {
                    this.showNotification('warning', 'Invalid File', 'Only images are supported');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const base64 = e.target.result.split(',')[1];
                    self.addImageToPreview({
                        base64: base64,
                        mimeType: file.type,
                        name: file.name
                    });
                };
                reader.readAsDataURL(file);
            });
        },

        addImageToPreview: function(imageData) {
            this.state.chatImages.push(imageData);
            
            const $preview = $('#chat-image-preview');
            $preview.show();
            
            const idx = this.state.chatImages.length - 1;
            const $img = $(`
                <div class="chat-preview-image" data-index="${idx}">
                    <img src="data:${imageData.mimeType};base64,${imageData.base64}" alt="${this.escapeHtml(imageData.name || 'Image')}">
                    <button class="chat-preview-remove" onclick="App.removePreviewImage(${idx})">×</button>
                </div>
            `);
            $preview.append($img);
        },

        removePreviewImage: function(index) {
            this.state.chatImages.splice(index, 1);
            this.refreshImagePreview();
        },

        refreshImagePreview: function() {
            const $preview = $('#chat-image-preview');
            $preview.empty();
            
            if (this.state.chatImages.length === 0) {
                $preview.hide();
                return;
            }
            
            this.state.chatImages.forEach((img, idx) => {
                const $img = $(`
                    <div class="chat-preview-image" data-index="${idx}">
                        <img src="data:${img.mimeType};base64,${img.base64}" alt="Image">
                        <button class="chat-preview-remove" onclick="App.removePreviewImage(${idx})">×</button>
                    </div>
                `);
                $preview.append($img);
            });
        },

        clearImagePreview: function() {
            this.state.chatImages = [];
            $('#chat-image-preview').empty().hide();
        },

        viewImage: function(img) {
            const src = $(img).attr('src');
            window.open(src, '_blank');
        },

        initChatDragDrop: function() {
            const self = this;
            const $chatMain = $('.chat-main');
            
            $chatMain.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            $chatMain.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            
            $chatMain.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleImageUpload(files);
                }
            });
        },

        initChatPaste: function() {
            const self = this;
            
            $('#chat-input').on('paste', function(e) {
                const items = (e.originalEvent.clipboardData || e.clipboardData).items;
                
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault();
                        const file = items[i].getAsFile();
                        self.handleImageUpload([file]);
                        break;
                    }
                }
            });
        },

        // Voice Input (Web Speech API)
        toggleVoiceInput: function() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                this.showNotification('warning', 'Not Supported', 'Voice input is not supported in this browser');
                return;
            }
            
            // Check for secure context (HTTPS or localhost)
            if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                this.showNotification('warning', 'Secure Context Required', 
                    'Voice input requires HTTPS or localhost. Current URL: ' + location.protocol + '//' + location.hostname);
            }
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (this.state.voiceRecognition && this.state.isRecording) {
                // Stop recording
                this.state.voiceRecognition.stop();
                this.state.isRecording = false;
                $('#voice-input-btn').removeClass('recording');
                return;
            }
            
            // Start recording
            this.state.voiceRecognition = new SpeechRecognition();
            this.state.voiceRecognition.continuous = false;
            this.state.voiceRecognition.interimResults = true;
            this.state.voiceRecognition.lang = 'en-US';
            
            const self = this;
            
            this.state.voiceRecognition.onstart = function() {
                self.state.isRecording = true;
                $('#voice-input-btn').addClass('recording');
                self.showNotification('info', 'Listening...', 'Speak now');
            };
            
            this.state.voiceRecognition.onresult = function(event) {
                let finalTranscript = '';
                let interimTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                
                // Update input with transcript
                const $input = $('#chat-input');
                const current = $input.val();
                $input.val(current + finalTranscript);
            };
            
            this.state.voiceRecognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                self.state.isRecording = false;
                $('#voice-input-btn').removeClass('recording');
                
                // Provide more helpful error messages
                let errorMessage = 'Voice input error: ' + event.error;
                let errorTitle = 'Error';
                
                if (event.error === 'network') {
                    errorTitle = 'Network Error';
                    errorMessage = 'Speech recognition requires HTTPS or localhost. In Brave browser, also check that:\n' +
                                   '1. Microphone permission is granted\n' +
                                   '2. Shields are down for this site\n' +
                                   '3. Google services are allowed in brave://settings/privacy';
                } else if (event.error === 'not-allowed' || event.error === 'permission-denied') {
                    errorTitle = 'Permission Denied';
                    errorMessage = 'Please allow microphone access in your browser settings';
                } else if (event.error === 'no-speech') {
                    errorTitle = 'No Speech Detected';
                    errorMessage = 'No speech was detected. Please try again.';
                    return; // Don't show error notification for no speech
                }
                
                self.showNotification('error', errorTitle, errorMessage);
            };
            
            this.state.voiceRecognition.onend = function() {
                self.state.isRecording = false;
                $('#voice-input-btn').removeClass('recording');
            };
            
            this.state.voiceRecognition.start();
        },

        // Smart Suggestions
        generateSuggestions: function() {
            const lastMessages = this.state.chatMessages.slice(-2);
            if (lastMessages.length === 0) return;
            
            const lastAssistant = lastMessages.find(m => m.role === 'assistant');
            if (!lastAssistant) return;
            
            // Generate contextual suggestions based on last response
            const suggestions = this.getContextualSuggestions(lastAssistant.content);
            
            if (suggestions.length > 0) {
                this.showSuggestions(suggestions);
            }
        },

        getContextualSuggestions: function(content) {
            const suggestions = [];
            const lowerContent = content.toLowerCase();
            
            // Context-aware suggestions
            if (lowerContent.includes('code') || lowerContent.includes('function') || lowerContent.includes('```')) {
                suggestions.push('Explain this code step by step');
                suggestions.push('How can I optimize this?');
                suggestions.push('Are there any bugs?');
            }
            
            if (lowerContent.includes('error') || lowerContent.includes('bug') || lowerContent.includes('issue')) {
                suggestions.push('How do I fix this?');
                suggestions.push('What causes this error?');
                suggestions.push('Show me an example solution');
            }
            
            if (lowerContent.includes('example') || lowerContent.includes('here is') || lowerContent.includes('here\'s')) {
                suggestions.push('Can you elaborate more?');
                suggestions.push('Show me another example');
                suggestions.push('What are the alternatives?');
            }
            
            if (lowerContent.includes('list') || lowerContent.includes('1.') || lowerContent.includes('- ')) {
                suggestions.push('Tell me more about the first one');
                suggestions.push('Which one do you recommend?');
                suggestions.push('Compare these options');
            }
            
            // General follow-ups
            if (suggestions.length < 3) {
                suggestions.push('Can you explain in simpler terms?');
                suggestions.push('What are the next steps?');
                suggestions.push('Tell me more');
            }
            
            return suggestions.slice(0, 4);
        },

        showSuggestions: function(suggestions) {
            const $container = $('#chat-suggestions');
            const $list = $('#suggestions-list');
            
            $list.empty();
            suggestions.forEach(s => {
                $list.append(`<span class="suggestion-chip" onclick="App.useSuggestion('${this.escapeHtml(s).replace(/'/g, "\\'")}')">${this.escapeHtml(s)}</span>`);
            });
            
            $container.show();
        },

        useSuggestion: function(text) {
            $('#chat-input').val(text).focus();
            $('#chat-suggestions').hide();
        },

        hideSuggestions: function() {
            $('#chat-suggestions').hide();
        },

        loadChatConversation: function(chatId) {
            $.get(this.api.history + '?action=get&id=' + encodeURIComponent(chatId))
                .done((response) => {
                    if (response.success && response.data) {
                        this.displayChatConversation(response.data);
                    } else {
                        this.showNotification('error', 'Error', 'Failed to load chat conversation');
                    }
                })
                .fail(() => {
                    this.showNotification('error', 'Error', 'Failed to load chat conversation');
                });
        },

        displayChatConversation: function(chatData) {
            // Clear current chat
            this.clearChat();
            
            // Set the model
            $('#chat-model-select').val(chatData.model);
            this.state.chatModel = chatData.model;
            
            // Parse and display messages
            let messages = [];
            try {
                messages = typeof chatData.messages === 'string' ? JSON.parse(chatData.messages) : chatData.messages;
            } catch (e) {
                console.warn('Failed to parse messages for chat:', chatData.id, e);
                this.showNotification('error', 'Error', 'Failed to parse chat messages');
                return;
            }
            
            // Display each message
            messages.forEach(message => {
                this.addChatMessage(message.role, message.content);
            });
            
            // Update state
            this.state.chatMessages = messages;
            
            // Show notification
            this.showNotification('success', 'Chat Loaded', `Loaded conversation with ${chatData.model}`);
        },

        // ============================================
        // GENERATE INTERFACE
        // ============================================
        openGenerateWithModel: function(modelName) {
            $('#generate-model-select').val(modelName);
            this.showWindow('generate');
        },

        submitGenerate: function() {
            const model = $('#generate-model-select').val();
            const prompt = $('#generate-prompt').val().trim();
            const systemPrompt = $('#generate-system').val().trim();
            const temperature = parseFloat($('#generate-temperature').val()) || 0.7;
            
            if (!model) {
                this.showNotification('warning', 'Select Model', 'Please select a model first');
                return;
            }
            
            if (!prompt) {
                this.showNotification('warning', 'Enter Prompt', 'Please enter a prompt');
                return;
            }
            
            const $result = $('#generate-result');
            const $stats = $('#generate-stats');
            
            $result.removeClass('empty').html('<div class="loading-spinner"></div>');
            $stats.html('');
            
            $.ajax({
                url: this.api.generate,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    model: model,
                    prompt: prompt,
                    options: {
                        system: systemPrompt || undefined,
                        temperature: temperature
                    }
                })
            })
            .done((response) => {
                if (response.success) {
                    $result.html(this.formatMessageContent(response.data.response));
                    
                    // Show stats
                    const totalDuration = response.data.total_duration ? 
                        (response.data.total_duration / 1000000000).toFixed(2) + 's' : '--';
                    const evalCount = response.data.eval_count || '--';
                    
                    $stats.html(`
                        <span>Duration: ${totalDuration}</span>
                        <span>Tokens: ${evalCount}</span>
                        <span>API Time: ${response.data.duration}ms</span>
                    `);
                } else {
                    $result.html('<span class="text-danger">Error: ' + this.escapeHtml(response.error) + '</span>');
                }
            })
            .fail(() => {
                $result.html('<span class="text-danger">Error: Failed to generate response</span>');
            });
        },

        // ============================================
        // EMBEDDINGS PLAYGROUND
        // ============================================
        generateEmbedding: function() {
            const model = $('#embed-model-select').val();
            const text = $('#embed-input').val().trim();
            
            if (!model) {
                this.showNotification('warning', 'Select Model', 'Please select a model first');
                return;
            }
            
            if (!text) {
                this.showNotification('warning', 'Enter Text', 'Please enter text to embed');
                return;
            }
            
            const $viz = $('#embed-visualization');
            $viz.html('<div class="flex items-center justify-center h-full"><div class="loading-spinner"></div></div>');
            
            $.ajax({
                url: this.api.embed,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ model: model, input: text })
            })
            .done((response) => {
                if (response.success) {
                    this.renderEmbeddingVisualization(response.data);
                } else {
                    $viz.html('<div class="empty-state"><div class="empty-state-title">Error</div><div class="empty-state-text">' + this.escapeHtml(response.error) + '</div></div>');
                }
            })
            .fail(() => {
                $viz.html('<div class="empty-state"><div class="empty-state-title">Failed</div></div>');
            });
        },

        renderEmbeddingVisualization: function(data) {
            const sample = data.stats.sample || [];
            const min = data.stats.min || -1;
            const max = data.stats.max || 1;
            
            // Create heatmap
            let heatmapHtml = '<div class="embed-heatmap">';
            sample.forEach(val => {
                const normalized = (val - min) / (max - min);
                const hue = Math.round(240 - normalized * 240); // Blue to red
                heatmapHtml += `<div class="embed-cell" style="background:hsl(${hue}, 70%, 50%)"></div>`;
            });
            heatmapHtml += '</div>';
            
            $('#embed-visualization').html(heatmapHtml);
            
            // Update stats
            $('#embed-stat-dims').text(data.dimensions);
            $('#embed-stat-min').text(data.stats.min?.toFixed(4) || 'N/A');
            $('#embed-stat-max').text(data.stats.max?.toFixed(4) || 'N/A');
            $('#embed-stat-mean').text(data.stats.mean?.toFixed(4) || 'N/A');
        },

        // ============================================
        // API LOGS
        // ============================================
        loadLogs: function() {
            $.get(this.api.logs + '?action=list&limit=100')
                .done((response) => {
                    if (response.success) {
                        this.renderLogs(response.data.logs);
                    }
                });
        },

        renderLogs: function(logs) {
            const $list = $('#logs-list');
            
            if (!logs || logs.length === 0) {
                $list.html('<div class="empty-state"><div class="empty-state-title">No logs yet</div></div>');
                return;
            }
            
            const html = logs.map(log => {
                const endpoint = log.endpoint.replace('/api/', '');
                return `
                    <div class="log-entry">
                        <span class="log-timestamp">${log.timestamp}</span>
                        <span class="log-endpoint ${endpoint}">${endpoint}</span>
                        <span class="log-preview">${this.escapeHtml(log.response_preview || '').substring(0, 100)}</span>
                        <span class="log-duration">${log.duration_ms}ms</span>
                    </div>
                `;
            }).join('');
            
            $list.html(html);
        },

        clearLogs: function() {
            if (!confirm('Clear all API logs?')) return;
            
            $.get(this.api.logs + '?action=clear')
                .done(() => {
                    this.loadLogs();
                    this.showNotification('success', 'Logs Cleared', 'All logs have been cleared');
                });
        },

        // ============================================
        // CHAT HISTORY
        // ============================================
        loadChatHistory: function() {
            $.get(this.api.history + '?action=list')
                .done((response) => {
                    if (response.success) {
                        this.renderChatHistory(response.data.history);
                    }
                });
        },

        renderChatHistory: function(history) {
            const $list = $('#chat-history-list');
            this.state.chatHistoryCache = history; // Cache for filtering
            
            if (!history || history.length === 0) {
                $list.html('<div class="empty-state p-3"><p class="text-muted">No chat history</p></div>');
                return;
            }
            
            const html = history.slice(0, 100).map(item => {
                // Parse messages JSON string
                let messages = [];
                try {
                    messages = typeof item.messages === 'string' ? JSON.parse(item.messages) : item.messages;
                } catch (e) {
                    console.warn('Failed to parse messages for chat item:', item.id, e);
                }
                
                // Get preview from first user message - truncate to 35 characters
                const userMessage = messages.find(m => m.role === 'user');
                let preview = userMessage ? userMessage.content : 'Empty';
                if (preview.length > 35) {
                    preview = preview.substring(0, 35) + '...';
                }
                const starred = item.starred ? 'starred' : '';
                
                return `
                    <div class="chat-history-item ${starred}" data-id="${item.id}">
                        <div class="chat-history-content">
                            <div class="chat-history-model">${this.escapeHtml(item.model)}</div>
                            <div class="chat-history-preview" title="${this.escapeHtml(userMessage ? userMessage.content.substring(0, 100) : '')}">${this.escapeHtml(preview)}</div>
                            <div class="chat-history-time">${item.timestamp}</div>
                        </div>
                        <div class="chat-history-actions">
                            <button class="chat-history-delete" data-id="${item.id}" title="Delete conversation">🗑️</button>
                        </div>
                    </div>
                `;
            }).join('');
            
            $list.html(html);
        },

        filterChatHistory: function(query) {
            if (!this.state.chatHistoryCache) return;
            
            const $countEl = $('#chat-search-count');
            const $clearBtn = $('#chat-search-clear');
            
            if (!query.trim()) {
                // When search is cleared, show all history (up to 100)
                $countEl.hide();
                $clearBtn.hide();
                this.renderChatHistory(this.state.chatHistoryCache);
                return;
            }
            
            // Show clear button when there's a search query
            $clearBtn.show();
            
            const filtered = this.state.chatHistoryCache.filter(item => {
                const messages = typeof item.messages === 'string' 
                    ? item.messages 
                    : JSON.stringify(item.messages);
                return messages.toLowerCase().includes(query.toLowerCase()) ||
                       item.model.toLowerCase().includes(query.toLowerCase());
            });
            
            // Show result count
            $countEl.text(`${filtered.length} result${filtered.length !== 1 ? 's' : ''} found`).show();
            
            this.renderChatHistory(filtered);
        },
        
        // Clear history search and reset to full history
        clearHistorySearch: function() {
            $('#chat-history-search').val('');
            $('#chat-search-clear').hide();
            $('#chat-search-count').hide();
            if (this.state.chatHistoryCache) {
                this.renderChatHistory(this.state.chatHistoryCache);
            } else {
                this.loadChatHistory();
            }
        },

        deleteChatHistory: function(chatId) {
            $.get(this.api.history + '?action=delete&id=' + encodeURIComponent(chatId))
                .done((response) => {
                    if (response.success) {
                        this.showNotification('success', 'Deleted', 'Conversation deleted successfully');
                        this.loadChatHistory(); // Refresh the history list
                        
                        // If the deleted chat was currently loaded, clear the chat
                        if (this.state.currentChatId === chatId) {
                            this.clearCurrentChat();
                        }
                    } else {
                        this.showNotification('error', 'Error', 'Failed to delete conversation');
                    }
                })
                .fail(() => {
                    this.showNotification('error', 'Error', 'Failed to delete conversation');
                });
        },

        clearCurrentChat: function() {
            this.state.chatMessages = [];
            this.state.currentChatId = null;
            $('#chat-messages').html(`
                <div class="chat-welcome">
                    <div class="chat-welcome-icon">🦙</div>
                    <h2>Welcome to Advanced Chat</h2>
                    <p>Select a model and start a conversation. Features include:</p>
                    <div class="chat-features-grid">
                        <div class="chat-feature">⚡ Real-time streaming</div>
                        <div class="chat-feature">🖼️ Image analysis</div>
                        <div class="chat-feature">📝 System prompts</div>
                        <div class="chat-feature">🔧 Tool calling</div>
                        <div class="chat-feature">📋 Structured output</div>
                        <div class="chat-feature">💾 Chat history</div>
                    </div>
                </div>
            `);
            $('#chat-stats').html('<span id="chat-token-count">Tokens: --</span><span id="chat-response-time">Response: --</span><span id="chat-message-count">Messages: 0</span>');
        },

        // ============================================
        // SETTINGS UI
        // ============================================
        saveSettingsForm: function() {
            const $btn = $('.settings-save-btn');
            $btn.prop('disabled', true).text('💾 Saving...');
            
            const settings = {
                theme: $('#setting-theme').val(),
                defaultModel: $('#setting-default-model').val(),
                temperature: parseFloat($('#setting-temperature').val()),
                maxTokens: parseInt($('#setting-max-tokens').val()),
                autoRefreshInterval: parseInt($('#setting-auto-refresh').val()),
                showNotifications: $('#setting-notifications').is(':checked'),
                ollamaHost: $('#setting-ollama-host').val().trim(),
                ollamaPort: $('#setting-ollama-port').val().trim()
            };
            
            this.saveSettings(settings)
                .done((response) => {
                    if (response.success) {
                        this.state.settings = settings;
                        this.applySettings();
                        this.startAutoRefresh();
                        
                        // Refresh dashboard and model dropdowns with new server settings
                        this.checkServerStatus();
                        this.loadModels();
                        
                        this.showNotification('success', 'Settings Saved', 'Your preferences have been saved and server connection updated');
                    }
                })
                .fail(() => {
                    this.showNotification('error', 'Error', 'Failed to save settings');
                })
                .always(() => {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                });
        },
        
        // Navigate to settings section
        scrollToSettingsSection: function(sectionId) {
            // Update active nav item
            $('.settings-nav-item').removeClass('active');
            $(`.settings-nav-item[data-section="${sectionId}"]`).addClass('active');
            
            // Scroll to section
            const $section = $(`#settings-section-${sectionId}`);
            if ($section.length) {
                const $content = $('.settings-content');
                const scrollTop = $section[0].offsetTop - $content[0].offsetTop;
                $content.animate({ scrollTop: scrollTop }, 300);
            }
        },

        // ============================================
        // WINDOW MANAGEMENT
        // ============================================
        initWindows: function() {
            // Make windows draggable and resizable
            $('.window').each((i, el) => {
                const $window = $(el);
                this.makeWindowDraggable($window);
                this.makeWindowResizable($window);
            });
            
            // Store window references
            $('.window').each((i, el) => {
                const id = $(el).attr('id');
                this.state.windows[id] = {
                    element: $(el),
                    minimized: false,
                    zIndex: 10
                };
            });
            
            // Initialize resizable panel divider for Model Creator
            this.initCreatorPanelResizer();
        },
        
        // Initialize resizable divider for creator panels
        initCreatorPanelResizer: function() {
            const $divider = $('#creator-panel-divider');
            const $sourcePanel = $('.source-panel');
            const $container = $('.creator-content');
            let isResizing = false;
            let startX = 0;
            let startWidth = 0;
            
            $divider.on('mousedown', function(e) {
                e.preventDefault();
                isResizing = true;
                startX = e.clientX;
                startWidth = $sourcePanel.width();
                $('body').addClass('resizing');
                $divider.addClass('active');
            });
            
            $(document).on('mousemove', function(e) {
                if (!isResizing) return;
                
                const containerWidth = $container.width();
                const deltaX = e.clientX - startX;
                let newWidth = startWidth + deltaX;
                
                // Constrain width between 20% and 60% of container
                const minWidth = containerWidth * 0.2;
                const maxWidth = containerWidth * 0.6;
                newWidth = Math.max(minWidth, Math.min(maxWidth, newWidth));
                
                const percentage = (newWidth / containerWidth) * 100;
                $sourcePanel.css('width', percentage + '%');
            });
            
            $(document).on('mouseup', function() {
                if (isResizing) {
                    isResizing = false;
                    $('body').removeClass('resizing');
                    $divider.removeClass('active');
                }
            });
        },

        initWindowZIndex: function() {
            // Initialize z-index tracking
            this.state.highestZIndex = 10;
            this.state.baseZIndex = 10;
        },

        makeWindowDraggable: function($window) {
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            
            $window.find('.window-titlebar').on('mousedown', (e) => {
                if ($(e.target).closest('.traffic-lights').length) return;
                
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                startLeft = $window.offset().left;
                startTop = $window.offset().top;
                
                this.focusWindow($window.attr('id'));
                $('body').addClass('dragging');
                
                $(document).on('mousemove.drag', (e) => {
                    if (!isDragging) return;
                    
                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;
                    
                    // Use translate for better performance, but fallback to left/top
                    // to maintain compatibility with resize operations
                    $window.css({
                        left: startLeft + dx + 'px',
                        top: startTop + dy + 'px'
                    });
                });
                
                $(document).on('mouseup.drag', () => {
                    isDragging = false;
                    $('body').removeClass('dragging');
                    $(document).off('.drag');
                });
            });
        },

        makeWindowResizable: function($window) {
            const self = this;
            let isResizing = false;
            let resizeDirection = '';
            let startX, startY, startWidth, startHeight, startLeft, startTop;
            const minWidth = 300;
            const minHeight = 200;
            
            $window.find('.window-resize-handle').on('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                isResizing = true;
                resizeDirection = $(this).attr('class').split(' ').find(cls => cls.startsWith('window-resize-') && cls !== 'window-resize-handle').replace('window-resize-', '');
                
                startX = e.clientX;
                startY = e.clientY;
                startWidth = $window.width();
                startHeight = $window.height();
                startLeft = $window.offset().left;
                startTop = $window.offset().top;
                
                self.focusWindow($window.attr('id'));
                $('body').addClass('resizing');
                
                let resizeFrame = null;
                $(document).on('mousemove.resize', (e) => {
                    if (!isResizing) return;
                    
                    // Cancel previous frame
                    if (resizeFrame) {
                        cancelAnimationFrame(resizeFrame);
                    }
                    
                    // Use requestAnimationFrame for smoother resizing
                    resizeFrame = requestAnimationFrame(() => {
                        const dx = e.clientX - startX;
                        const dy = e.clientY - startY;
                        
                        let newWidth = startWidth;
                        let newHeight = startHeight;
                        let newLeft = startLeft;
                        let newTop = startTop;
                        
                        // Handle different resize directions
                        // EAST (right edge) - drag right to make wider
                        if (resizeDirection.includes('e')) {
                            newWidth = Math.max(minWidth, startWidth + dx);
                        }
                        // WEST (left edge) - drag left to make wider, moves window left
                        if (resizeDirection.includes('w')) {
                            const proposedWidth = startWidth - dx;
                            if (proposedWidth >= minWidth) {
                                newWidth = proposedWidth;
                                newLeft = startLeft + dx;
                            } else {
                                newWidth = minWidth;
                                newLeft = startLeft + startWidth - minWidth;
                            }
                        }
                        // SOUTH (bottom edge) - drag down to make taller
                        if (resizeDirection.includes('s')) {
                            newHeight = Math.max(minHeight, startHeight + dy);
                        }
                        // NORTH (top edge) - drag up to make taller, moves window up
                        if (resizeDirection.includes('n')) {
                            const proposedHeight = startHeight - dy;
                            if (proposedHeight >= minHeight) {
                                newHeight = proposedHeight;
                                newTop = startTop + dy;
                            } else {
                                newHeight = minHeight;
                                newTop = startTop + startHeight - minHeight;
                            }
                        }
                        
                        $window.css({
                            width: newWidth + 'px',
                            height: newHeight + 'px',
                            left: newLeft + 'px',
                            top: newTop + 'px'
                        });
                    });
                });
                
                $(document).on('mouseup.resize', () => {
                    isResizing = false;
                    resizeDirection = '';
                    $('body').removeClass('resizing');
                    $(document).off('.resize');
                });
            });
        },

        showWindow: function(windowId) {
            const $window = $('#window-' + windowId);
            
            if (!$window.length) return;
            
            // Show and restore from minimized state
            $window.addClass('visible').removeClass('minimized');
            
            // Focus the window (brings to front)
            this.focusWindow('window-' + windowId);
            
            // Update dock
            $('.dock-item').removeClass('active');
            $(`.dock-item[data-window="${windowId}"]`).addClass('active');
            
            // Update state
            if (this.state.windows['window-' + windowId]) {
                this.state.windows['window-' + windowId].minimized = false;
            }
            
            // Load content based on window
            switch(windowId) {
                case 'models':
                    this.loadModels();
                    break;
                case 'chat':
                    this.loadChatHistory();
                    this.loadSystemPrompts();
                    break;
                case 'logs':
                    this.loadLogs();
                    break;
                case 'settings':
                    // Model selector in settings should already be populated from init
                    break;
                case 'modelcreator':
                    // Ensure model list is updated in creator dropdown
                    this.loadModels();
                    break;
            }
        },

        hideWindow: function(windowId) {
            const $window = $('#' + windowId);
            $window.removeClass('visible active');
            
            // If this was the active window, clear active state
            if (this.state.activeWindow === windowId) {
                this.state.activeWindow = null;
            }
            
            // Update dock
            $(`.dock-item[data-window="${windowId.replace('window-', '')}"]`).removeClass('active');
        },

        minimizeWindow: function(windowId) {
            const $window = $('#' + windowId);
            $window.addClass('minimized');
            
            if (this.state.windows[windowId]) {
                this.state.windows[windowId].minimized = true;
            }
        },

        maximizeWindow: function(windowId) {
            const $window = $('#' + windowId);
            const $desktop = $('.app-desktop');
            
            if ($window.hasClass('maximized')) {
                // Restore
                $window.removeClass('maximized').css({
                    top: $window.data('restore-top'),
                    left: $window.data('restore-left'),
                    width: $window.data('restore-width'),
                    height: $window.data('restore-height')
                });
                // Re-enable resize handles
                $window.find('.window-resize-handle').css('display', '');
            } else {
                // Maximize
                $window.data({
                    'restore-top': $window.css('top'),
                    'restore-left': $window.css('left'),
                    'restore-width': $window.css('width'),
                    'restore-height': $window.css('height')
                });
                
                $window.addClass('maximized').css({
                    top: 0,
                    left: 0,
                    width: '100%',
                    height: '100%'
                });
                // Hide resize handles when maximized
                $window.find('.window-resize-handle').css('display', 'none');
            }
            
            // Ensure window stays focused
            this.focusWindow(windowId);
        },

        focusWindow: function(windowId) {
            const $window = $('#' + windowId);
            if (!$window.length) return;
            
            // Remove active from all windows
            $('.window').removeClass('active');
            
            // Increment z-index for proper stacking
            this.state.highestZIndex++;
            $window.css('z-index', this.state.highestZIndex);
            $window.addClass('active');
            
            // Update window state
            if (this.state.windows[windowId]) {
                this.state.windows[windowId].zIndex = this.state.highestZIndex;
            }
            
            this.state.activeWindow = windowId;
        },

        // ============================================
        // SPOTLIGHT SEARCH
        // ============================================
        toggleSpotlight: function() {
            this.state.spotlightVisible = !this.state.spotlightVisible;
            
            if (this.state.spotlightVisible) {
                $('#spotlight-overlay').addClass('visible');
                $('#spotlight-input').val('').focus();
                this.updateSpotlightResults('');
            } else {
                $('#spotlight-overlay').removeClass('visible');
            }
        },

        updateSpotlightResults: function(query) {
            const $results = $('#spotlight-results');
            
            // Define searchable items
            const items = [
                { type: 'window', icon: '📊', title: 'Dashboard', subtitle: 'View server status', action: () => this.showWindow('dashboard') },
                { type: 'window', icon: '📦', title: 'Model Manager', subtitle: 'Manage your models', action: () => this.showWindow('models') },
                { type: 'window', icon: '�️', title: 'Model Creator', subtitle: 'Create custom models with parameters', action: () => this.showWindow('modelcreator') },
                { type: 'window', icon: '�💬', title: 'Chat', subtitle: 'Chat with models', action: () => this.showWindow('chat') },
                { type: 'window', icon: '✨', title: 'Generate', subtitle: 'Generate completions', action: () => this.showWindow('generate') },
                { type: 'window', icon: '🎯', title: 'Embeddings', subtitle: 'Vector embeddings playground', action: () => this.showWindow('embeddings') },
                { type: 'window', icon: '📋', title: 'API Logs', subtitle: 'View API request logs', action: () => this.showWindow('logs') },
                { type: 'window', icon: '⚙️', title: 'Settings', subtitle: 'Configure preferences', action: () => this.showWindow('settings') },
                { type: 'action', icon: '📥', title: 'Pull Model', subtitle: 'Download a new model', action: () => { this.toggleSpotlight(); this.pullModel(); } },
                { type: 'action', icon: '🔄', title: 'Refresh Status', subtitle: 'Check server connection', action: () => { this.toggleSpotlight(); this.checkServerStatus(); } }
            ];
            
            // Add models to search
            this.state.models.forEach(model => {
                items.push({
                    type: 'model',
                    icon: '🤖',
                    title: model.name,
                    subtitle: model.size_formatted,
                    action: () => { this.toggleSpotlight(); this.openChatWithModel(model.name); }
                });
            });
            
            // Filter by query
            const filtered = query ? items.filter(item => 
                item.title.toLowerCase().includes(query.toLowerCase()) ||
                item.subtitle.toLowerCase().includes(query.toLowerCase())
            ) : items.slice(0, 8);
            
            if (filtered.length === 0) {
                $results.html('<div class="spotlight-result"><div class="spotlight-result-info"><div class="spotlight-result-title">No results</div></div></div>');
                return;
            }
            
            const html = filtered.slice(0, 10).map((item, index) => `
                <div class="spotlight-result ${index === 0 ? 'selected' : ''}" data-index="${index}">
                    <div class="spotlight-result-icon">${item.icon}</div>
                    <div class="spotlight-result-info">
                        <div class="spotlight-result-title">${this.escapeHtml(item.title)}</div>
                        <div class="spotlight-result-subtitle">${this.escapeHtml(item.subtitle)}</div>
                    </div>
                </div>
            `).join('');
            
            $results.html(html);
            
            // Store actions for selection
            this.spotlightItems = filtered.slice(0, 10);
            this.spotlightSelectedIndex = 0;
        },

        selectSpotlightResult: function() {
            if (this.spotlightItems && this.spotlightItems[this.spotlightSelectedIndex]) {
                this.spotlightItems[this.spotlightSelectedIndex].action();
                this.toggleSpotlight();
            }
        },

        navigateSpotlight: function(direction) {
            if (!this.spotlightItems) return;
            
            const maxIndex = this.spotlightItems.length - 1;
            this.spotlightSelectedIndex += direction;
            
            if (this.spotlightSelectedIndex < 0) this.spotlightSelectedIndex = maxIndex;
            if (this.spotlightSelectedIndex > maxIndex) this.spotlightSelectedIndex = 0;
            
            $('.spotlight-result').removeClass('selected');
            $(`.spotlight-result[data-index="${this.spotlightSelectedIndex}"]`).addClass('selected');
        },

        // ============================================
        // NOTIFICATIONS
        // ============================================
        showNotification: function(type, title, message) {
            if (!this.state.settings.showNotifications && type !== 'error') return;
            
            const $notification = $(`
                <div class="notification">
                    <div class="notification-icon ${type}">${this.getNotificationIcon(type)}</div>
                    <div class="notification-content">
                        <div class="notification-title">${this.escapeHtml(title)}</div>
                        <div class="notification-message">${this.escapeHtml(message)}</div>
                    </div>
                </div>
            `);
            
            $('#notification-container').append($notification);
            
            // Animate in
            setTimeout(() => $notification.addClass('visible'), 10);
            
            // Auto dismiss
            setTimeout(() => {
                $notification.removeClass('visible');
                setTimeout(() => $notification.remove(), 300);
            }, 4000);
            
            // Click to dismiss
            $notification.on('click', () => {
                $notification.removeClass('visible');
                setTimeout(() => $notification.remove(), 300);
            });
        },

        getNotificationIcon: function(type) {
            switch(type) {
                case 'success': return '✓';
                case 'error': return '✕';
                case 'warning': return '⚠';
                case 'info': return 'ℹ';
                default: return '•';
            }
        },

        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        initKeyboardShortcuts: function() {
            $(document).on('keydown', (e) => {
                // Cmd/Ctrl + K for Spotlight
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.toggleSpotlight();
                }
                
                // Cmd/Ctrl + H for Chat History Toggle
                if ((e.metaKey || e.ctrlKey) && e.key === 'h') {
                    e.preventDefault();
                    // Only toggle if chat window is active
                    if (this.state.activeWindow === 'window-chat' || $('#window-chat').hasClass('visible')) {
                        this.toggleChatSidebar();
                    }
                }
                
                // Escape to close spotlight or windows
                if (e.key === 'Escape') {
                    if (this.state.spotlightVisible) {
                        this.toggleSpotlight();
                    }
                }
                
                // Arrow navigation in spotlight
                if (this.state.spotlightVisible) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.navigateSpotlight(1);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.navigateSpotlight(-1);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        this.selectSpotlightResult();
                    }
                }
                
                // Cmd/Ctrl + 1-9 for windows
                if ((e.metaKey || e.ctrlKey) && e.key >= '1' && e.key <= '9') {
                    e.preventDefault();
                    const windows = ['dashboard', 'models', 'modelcreator', 'chat', 'generate', 'embeddings', 'logs', 'compare', 'settings'];
                    const index = parseInt(e.key) - 1;
                    if (windows[index]) {
                        this.showWindow(windows[index]);
                    }
                }
                
                // Cmd/Ctrl + Shift + N for new model creator
                if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key === 'N') {
                    e.preventDefault();
                    this.showWindow('modelcreator');
                }
            });
        },

        // ============================================
        // EVENT BINDINGS
        // ============================================
        bindEvents: function() {
            const self = this;
            
            // Dock clicks
            $(document).on('click', '.dock-item[data-window]', function() {
                const windowId = $(this).data('window');
                self.showWindow(windowId);
            });
            
            // Window controls
            $(document).on('click', '.traffic-light.close', function() {
                const windowId = $(this).closest('.window').attr('id');
                self.hideWindow(windowId);
            });
            
            $(document).on('click', '.traffic-light.minimize', function() {
                const windowId = $(this).closest('.window').attr('id');
                self.minimizeWindow(windowId);
            });
            
            $(document).on('click', '.traffic-light.maximize', function() {
                const windowId = $(this).closest('.window').attr('id');
                self.maximizeWindow(windowId);
            });
            
            // Window focus - on any click within window
            $(document).on('mousedown', '.window', function(e) {
                // Don't refocus if clicking on a resize handle (it already focuses)
                if (!$(e.target).hasClass('window-resize-handle')) {
                    self.focusWindow($(this).attr('id'));
                }
            });
            
            // Window content area should also focus
            $(document).on('mousedown', '.window-content', function(e) {
                self.focusWindow($(this).closest('.window').attr('id'));
            });
            
            // Model list clicks
            $(document).on('click', '.model-item', function() {
                const modelName = $(this).data('model');
                if (modelName) {
                    self.selectModel(modelName);
                }
            });
            
            // Model search
            $(document).on('input', '#model-search', function() {
                self.renderModelList();
            });
            
            // Chat input
            $(document).on('keydown', '#chat-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendChatMessage();
                }
            });
            
            $(document).on('click', '#chat-send-btn', function() {
                self.sendChatMessage();
            });
            
            // Chat history item clicks
            $(document).on('click', '.chat-history-item', function() {
                const chatId = $(this).data('id');
                if (chatId) {
                    self.loadChatConversation(chatId);
                }
            });
            
            // Chat history delete button
            $(document).on('click', '.chat-history-delete', function(e) {
                e.stopPropagation(); // Prevent triggering the parent click
                const chatId = $(this).data('id');
                if (chatId && confirm('Are you sure you want to delete this conversation?')) {
                    self.deleteChatHistory(chatId);
                }
            });
            
            // Chat history search with debounce
            let searchDebounceTimer = null;
            $(document).on('input', '#chat-history-search', function() {
                const query = $(this).val();
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(function() {
                    self.filterChatHistory(query);
                }, 200);
            });
            
            // Spotlight
            $(document).on('input', '#spotlight-input', function() {
                self.updateSpotlightResults($(this).val());
            });
            
            $(document).on('click', '.spotlight-result', function() {
                self.spotlightSelectedIndex = parseInt($(this).data('index'));
                self.selectSpotlightResult();
            });
            
            $(document).on('click', '#spotlight-overlay', function(e) {
                if (e.target === this) {
                    self.toggleSpotlight();
                }
            });
            
            // Menu bar
            $(document).on('click', '.menubar-spotlight', function() {
                self.toggleSpotlight();
            });
            
            // Theme toggle in menu
            $(document).on('click', '.menubar-theme-toggle', function() {
                const newTheme = self.state.settings.theme === 'dark' ? 'aqua' : 'dark';
                self.state.settings.theme = newTheme;
                self.applySettings();
                self.saveSettings({ theme: newTheme });
            });
            
            // Terminal input
            $(document).on('keydown', '#terminal-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.executeTerminalCommand();
                }
            });
            
            // Compare model selectors
            $(document).on('change', '#compare-model-1, #compare-model-2', function() {
                self.updateCompareButton();
            });
            
            // Model Creator - Parameter inputs change tracking
            $(document).on('input', '.creator-param input', function() {
                const paramName = $(this).attr('id').replace('creator-', '');
                const value = $(this).val();
                self.updateParamDiff(paramName, value);
            });
            
            // Model Creator - Source model selection change
            $(document).on('change', '#creator-source-model', function() {
                // Clear source params when model changes
                self.sourceModelParams = {};
                $('#creator-source-params').html(`
                    <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 12px;">📦</div>
                        <div style="font-size: 13px; color: var(--text-muted);">Click Load to view parameters</div>
                    </div>
                `);
                $('#creator-source-name').text('No model loaded');
            });
        },

        // ============================================
        // MODEL COMPARISON
        // ============================================
        updateCompareButton: function() {
            const model1 = $('#compare-model-1').val();
            const model2 = $('#compare-model-2').val();
            $('#compare-run-btn').prop('disabled', !model1 || !model2);
        },

        runComparison: function() {
            const model1 = $('#compare-model-1').val();
            const model2 = $('#compare-model-2').val();
            const prompt = $('#compare-prompt').val().trim();
            
            if (!model1 || !model2) {
                this.showNotification('warning', 'Select Models', 'Please select two models to compare');
                return;
            }
            
            if (!prompt) {
                this.showNotification('warning', 'Enter Prompt', 'Please enter a prompt for comparison');
                return;
            }
            
            // Clear and show loading for both
            $('#compare-result-1, #compare-result-2').html('<div class="loading-spinner"></div>');
            $('#compare-stats-1, #compare-stats-2').html('');
            
            // Run both models in parallel
            const self = this;
            const startTime1 = Date.now();
            const startTime2 = Date.now();
            
            // Model 1
            $.ajax({
                url: this.api.generate,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ model: model1, prompt: prompt })
            }).done((response) => {
                const duration = Date.now() - startTime1;
                if (response.success) {
                    $('#compare-result-1').html(self.formatMessageContent(response.data.response));
                    const tokens = response.data.eval_count || '--';
                    $('#compare-stats-1').html(`Tokens: ${tokens} | Time: ${(duration/1000).toFixed(2)}s | API: ${response.data.duration}ms`);
                } else {
                    $('#compare-result-1').html('<span class="text-danger">' + response.error + '</span>');
                }
            }).fail(() => {
                $('#compare-result-1').html('<span class="text-danger">Request failed</span>');
            });
            
            // Model 2
            $.ajax({
                url: this.api.generate,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ model: model2, prompt: prompt })
            }).done((response) => {
                const duration = Date.now() - startTime2;
                if (response.success) {
                    $('#compare-result-2').html(self.formatMessageContent(response.data.response));
                    const tokens = response.data.eval_count || '--';
                    $('#compare-stats-2').html(`Tokens: ${tokens} | Time: ${(duration/1000).toFixed(2)}s | API: ${response.data.duration}ms`);
                } else {
                    $('#compare-result-2').html('<span class="text-danger">' + response.error + '</span>');
                }
            }).fail(() => {
                $('#compare-result-2').html('<span class="text-danger">Request failed</span>');
            });
        },

        // ============================================
        // TERMINAL / RAW API
        // ============================================
        executeTerminalCommand: function() {
            const $input = $('#terminal-input');
            const $output = $('#terminal-output');
            const command = $input.val().trim();
            
            if (!command) return;
            
            // Add command to output
            $output.append(`<div class="terminal-line command">$ ${this.escapeHtml(command)}</div>`);
            $input.val('');
            
            // Parse command
            const parts = command.split(' ');
            const cmd = parts[0].toLowerCase();
            const args = parts.slice(1);
            
            switch(cmd) {
                case 'help':
                    this.terminalOutput($output, `
Available commands:
  status          - Check server status
  models          - List all models
  running         - List running models
  show <model>    - Show model details
  pull <model>    - Pull a model
  delete <model>  - Delete a model
  version         - Show Ollama version
  clear           - Clear terminal
  help            - Show this help
                    `);
                    break;
                    
                case 'clear':
                    $output.empty();
                    break;
                    
                case 'status':
                    this.terminalApiCall($output, this.api.status);
                    break;
                    
                case 'models':
                    this.terminalApiCall($output, this.api.models + '?action=list');
                    break;
                    
                case 'running':
                    this.terminalApiCall($output, this.api.models + '?action=running');
                    break;
                    
                case 'version':
                    $.get(this.api.status).done((r) => {
                        if (r.success) {
                            this.terminalOutput($output, `Ollama version: ${r.data.server.version || 'Unknown'}`);
                        }
                    });
                    break;
                    
                case 'show':
                    if (args[0]) {
                        this.terminalApiCall($output, this.api.models + '?action=show&model=' + encodeURIComponent(args[0]));
                    } else {
                        this.terminalOutput($output, 'Usage: show <model_name>', 'error');
                    }
                    break;
                    
                case 'pull':
                    if (args[0]) {
                        this.terminalOutput($output, `Pulling ${args[0]}... (this may take a while)`);
                        $.ajax({
                            url: this.api.models + '?action=pull',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ model: args[0] })
                        }).done((r) => {
                            this.terminalOutput($output, JSON.stringify(r, null, 2));
                        }).fail(() => {
                            this.terminalOutput($output, 'Pull failed', 'error');
                        });
                    } else {
                        this.terminalOutput($output, 'Usage: pull <model_name>', 'error');
                    }
                    break;
                    
                case 'delete':
                    if (args[0]) {
                        $.ajax({
                            url: this.api.models + '?action=delete',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ model: args[0] })
                        }).done((r) => {
                            this.terminalOutput($output, JSON.stringify(r, null, 2));
                        }).fail(() => {
                            this.terminalOutput($output, 'Delete failed', 'error');
                        });
                    } else {
                        this.terminalOutput($output, 'Usage: delete <model_name>', 'error');
                    }
                    break;
                    
                default:
                    this.terminalOutput($output, `Unknown command: ${cmd}. Type 'help' for available commands.`, 'error');
            }
            
            // Scroll to bottom
            $output.scrollTop($output[0].scrollHeight);
        },

        terminalOutput: function($output, text, type = 'normal') {
            const className = type === 'error' ? 'terminal-line error' : 'terminal-line';
            $output.append(`<div class="${className}"><pre>${this.escapeHtml(text.trim())}</pre></div>`);
            $output.scrollTop($output[0].scrollHeight);
        },

        terminalApiCall: function($output, url) {
            $.get(url).done((response) => {
                this.terminalOutput($output, JSON.stringify(response, null, 2));
            }).fail(() => {
                this.terminalOutput($output, 'API request failed', 'error');
            });
        },

        // ============================================
        // UTILITIES
        // ============================================
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        formatBytes: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.App = App;
        App.init();
    });

})(jQuery);
