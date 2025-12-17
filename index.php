<?php
// Include config to get constants
require_once __DIR__ . '/api/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ollama Manager - A beautiful Aqua-styled dashboard for remote Ollama server management">
    <title>Ollama Manager</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/aqua.css">
    <link rel="stylesheet" href="assets/css/app.css">
    
    <!-- Highlight.js for code syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🦙</text></svg>">
    
    <style>
        /* Initial loading state */
        .app-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, #3B6EA5 0%, #6B9BC3 50%, #8FB5D5 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            transition: opacity 0.5s ease;
        }
        .app-loading.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .app-loading-logo {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        .app-loading-text {
            color: white;
            font-size: 18px;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="app-loading" id="app-loading">
        <div class="app-loading-logo">🦙</div>
        <div class="app-loading-text">Loading Ollama Manager...</div>
    </div>

    <!-- Menu Bar -->
    <div class="menubar">
        <div class="menubar-left">
            <span class="menubar-item menubar-apple">🦙</span>
            <span class="menubar-item"><strong>Ollama Manager</strong></span>
            <span class="menubar-item">File</span>
            <span class="menubar-item">Edit</span>
            <span class="menubar-item">View</span>
            <span class="menubar-item">Help</span>
        </div>
        <div class="menubar-right">
            <span class="menubar-item menubar-status">
                <span class="status-dot" style="background:#999"></span> Checking...
            </span>
            <span class="menubar-item menubar-spotlight" title="Search (⌘K)">🔍</span>
            <span class="menubar-item menubar-theme-toggle" title="Toggle Theme">🌓</span>
            <span class="menubar-item" id="menubar-time"></span>
        </div>
    </div>

    <!-- Desktop Area -->
    <div class="app-desktop">
        
        <!-- ==================== DASHBOARD WINDOW ==================== -->
        <div class="window" id="window-dashboard" style="width: 900px; height: 600px; top: 30px; left: 50px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">📊 Dashboard</div>
            </div>
            <div class="window-content" style="overflow-y: auto;">
                <!-- Server Status Banner -->
                <div style="padding: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 4px;">Ollama Manager</h1>
                            <p style="opacity: 0.9; font-size: 13px;">Remote server management dashboard</p>
                        </div>
                        <div class="server-status status-indicator offline">
                            <span class="status-dot"></span>
                            <span class="status-text">Checking...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">📦</div>
                        <div class="stat-info">
                            <h3 id="stat-models-total">--</h3>
                            <p>Total Models</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">⚡</div>
                        <div class="stat-info">
                            <h3 id="stat-models-running">--</h3>
                            <p>Running Models</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">💾</div>
                        <div class="stat-info">
                            <h3 id="stat-storage">--</h3>
                            <p>Storage Used</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">📡</div>
                        <div class="stat-info">
                            <h3 id="stat-latency">--</h3>
                            <p>Latency</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div style="padding: 0 16px;">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px;">Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <div class="quick-action" onclick="App.showWindow('chat')">
                        <div class="quick-action-icon">💬</div>
                        <div class="quick-action-label">Start Chat</div>
                    </div>
                    <div class="quick-action" onclick="App.showWindow('generate')">
                        <div class="quick-action-icon">✨</div>
                        <div class="quick-action-label">Generate</div>
                    </div>
                    <div class="quick-action" onclick="App.showWindow('models')">
                        <div class="quick-action-icon">📦</div>
                        <div class="quick-action-label">Models</div>
                    </div>
                    <div class="quick-action" onclick="App.pullModel()">
                        <div class="quick-action-icon">📥</div>
                        <div class="quick-action-label">Pull Model</div>
                    </div>
                    <div class="quick-action" onclick="App.showWindow('modelcreator')">
                        <div class="quick-action-icon">🛠️</div>
                        <div class="quick-action-label">Create Model</div>
                    </div>
                    <div class="quick-action" onclick="App.showWindow('embeddings')">
                        <div class="quick-action-icon">🎯</div>
                        <div class="quick-action-label">Embeddings</div>
                    </div>
                    <div class="quick-action" onclick="App.checkServerStatus()">
                        <div class="quick-action-icon">🔄</div>
                        <div class="quick-action-label">Refresh</div>
                    </div>
                </div>
                
                <!-- Running Models Section -->
                <div style="padding: 16px;">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px;">Running Models</h3>
                    <div id="running-models-list" class="aqua-card">
                        <div class="empty-state p-3">
                            <p>Checking for running models...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Server Info -->
                <div style="padding: 16px;">
                    <h3 style="font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px;">Server Information</h3>
                    <div class="aqua-card">
                        <div class="aqua-card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Host</div>
                                    <div style="font-family: var(--font-mono); font-size: 13px;"><?php echo OLLAMA_HOST ?? '192.168.1.134'; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Port</div>
                                    <div style="font-family: var(--font-mono); font-size: 13px;"><?php echo OLLAMA_PORT ?? '11434'; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">Version</div>
                                    <div style="font-family: var(--font-mono); font-size: 13px;" id="stat-version">--</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="window-footer">
                <span>Ollama Manager v1.0.0</span>
                <span style="margin-left: auto;">Press ⌘K for Spotlight Search</span>
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

        <!-- ==================== MODEL MANAGER WINDOW ==================== -->
        <div class="window" id="window-models" style="width: 950px; height: 650px; top: 50px; left: 100px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">📦 Model Manager</div>
            </div>
            <div class="window-toolbar">
                <button class="aqua-btn primary" onclick="App.pullModel()">
                    📥 Pull Model
                </button>
                <button class="aqua-btn" onclick="App.showWindow('modelcreator')">
                    🛠️ Create Custom
                </button>
                <button class="aqua-btn" onclick="App.loadModels()">
                    🔄 Refresh
                </button>
            </div>
            <div class="window-content">
                <div class="model-list-container">
                    <div class="model-sidebar">
                        <div class="model-sidebar-header">
                            <input type="text" id="model-search" class="aqua-input model-sidebar-search" placeholder="Search models...">
                        </div>
                        <div class="model-list" id="model-list">
                            <div class="empty-state">
                                <div class="loading-spinner"></div>
                            </div>
                        </div>
                    </div>
                    <div class="model-details" id="model-details">
                        <div class="empty-state">
                            <div class="empty-state-icon">👈</div>
                            <div class="empty-state-title">Select a model</div>
                            <div class="empty-state-text">Choose a model from the list to view details</div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== CHAT WINDOW ==================== -->
        <div class="window" id="window-chat" style="width: 950px; height: 700px; top: 50px; left: 100px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">💬 Advanced Chat</div>
            </div>
            <div class="window-content">
                <div class="chat-container">
                    <!-- Chat Sidebar (hidden by default) -->
                    <div class="chat-sidebar" id="chat-sidebar" style="display: none;">
                        <div class="chat-sidebar-header">
                            <span style="font-weight: 600; font-size: 13px;">💬 History</span>
                            <div style="display: flex; gap: 4px;">
                                <button class="aqua-btn small" onclick="App.createNewChat()" title="New Chat">+ New</button>
                                <button class="aqua-btn small" onclick="App.toggleChatSidebar()" title="Hide History (Ctrl+H)">✕</button>
                            </div>
                        </div>
                        <div class="chat-sidebar-search">
                            <input type="text" id="chat-history-search" class="aqua-input" placeholder="Search chats..." style="width: 100%; font-size: 12px;">
                        </div>
                        <div class="chat-history-list" id="chat-history-list">
                            <div class="empty-state p-3">
                                <p class="text-muted">No chat history</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resizable Divider for Chat Sidebar -->
                    <div class="chat-sidebar-divider" id="chat-sidebar-divider" style="display: none;"></div>
                    
                    <!-- Chat Main Area -->
                    <div class="chat-main">
                        <!-- Chat Header with Model Selection and Options -->
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <button class="aqua-btn small" id="chat-history-toggle" onclick="App.toggleChatSidebar()" title="Toggle History (Ctrl+H)">
                                    📋 History
                                </button>
                                <div class="chat-model-selector">
                                    <label style="font-size: 11px; color: var(--text-secondary);">Model:</label>
                                    <select id="chat-model-select" class="aqua-select model-selector" style="width: 180px;">
                                        <option value="">Select model...</option>
                                    </select>
                                </div>
                                <div class="chat-preset-selector">
                                    <label style="font-size: 11px; color: var(--text-secondary);">Persona:</label>
                                    <select id="chat-preset-select" class="aqua-select" style="width: 160px;">
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="chat-header-right">
                                <button class="aqua-btn small" id="chat-options-toggle" onclick="App.toggleChatOptions()" title="Options">
                                    ⚙️ Options
                                </button>
                                <button class="aqua-btn small" onclick="App.exportChat()" title="Export">
                                    📤 Export
                                </button>
                                <button class="aqua-btn small danger" onclick="App.clearChat()" title="Clear">
                                    🗑️ Clear
                                </button>
                            </div>
                        </div>
                        
                        <!-- Collapsible Options Panel -->
                        <div class="chat-options-panel" id="chat-options-panel" style="display: none;">
                            <!-- System Prompt -->
                            <div class="chat-option-group">
                                <label class="chat-option-label">
                                    <span>📝 System Prompt</span>
                                    <button class="aqua-btn tiny" onclick="App.saveSystemPrompt()" title="Save as preset">💾</button>
                                </label>
                                <textarea id="chat-system-prompt" class="aqua-textarea" rows="2" placeholder="You are a helpful assistant..."></textarea>
                            </div>
                            
                            <!-- Model Parameters -->
                            <div class="chat-options-grid">
                                <div class="chat-option-item">
                                    <label>🌡️ Temperature: <span id="chat-temp-value">0.7</span></label>
                                    <input type="range" id="chat-temperature" class="aqua-slider" min="0" max="2" step="0.1" value="0.7">
                                    <div class="chat-temp-presets">
                                        <button class="aqua-btn tiny" onclick="App.setChatTemp(0.1)">Precise</button>
                                        <button class="aqua-btn tiny" onclick="App.setChatTemp(0.7)">Balanced</button>
                                        <button class="aqua-btn tiny" onclick="App.setChatTemp(1.2)">Creative</button>
                                    </div>
                                </div>
                                <div class="chat-option-item">
                                    <label>📊 Max Tokens</label>
                                    <input type="number" id="chat-max-tokens" class="aqua-input" value="8192" min="1" max="128000">
                                </div>
                                <div class="chat-option-item">
                                    <label>🪟 Context Size</label>
                                    <input type="number" id="chat-context-size" class="aqua-input" value="16384" min="512" max="131072">
                                </div>
                                <div class="chat-option-item">
                                    <label>🎲 Seed (reproducibility)</label>
                                    <input type="number" id="chat-seed" class="aqua-input" value="" placeholder="Random">
                                </div>
                                <div class="chat-option-item">
                                    <label>📈 Top P</label>
                                    <input type="number" id="chat-top-p" class="aqua-input" value="0.9" min="0" max="1" step="0.05">
                                </div>
                                <div class="chat-option-item">
                                    <label>📉 Top K</label>
                                    <input type="number" id="chat-top-k" class="aqua-input" value="40" min="1" max="100">
                                </div>
                            </div>
                            
                            <!-- Feature Toggles -->
                            <div class="chat-feature-toggles">
                                <label class="aqua-checkbox-inline">
                                    <input type="checkbox" id="chat-streaming-enabled" checked>
                                    <span>⚡ Streaming</span>
                                </label>
                                <label class="aqua-checkbox-inline">
                                    <input type="checkbox" id="chat-structured-output">
                                    <span>📋 Structured Output</span>
                                </label>
                                <label class="aqua-checkbox-inline">
                                    <input type="checkbox" id="chat-tools-enabled">
                                    <span>🔧 Enable Tools</span>
                                </label>
                            </div>
                            
                            <!-- Structured Output Schema (hidden by default) -->
                            <div id="chat-schema-section" style="display: none;">
                                <label class="chat-option-label">JSON Schema</label>
                                <div class="chat-schema-presets">
                                    <button class="aqua-btn tiny" onclick="App.loadSchemaPreset('person')">Person</button>
                                    <button class="aqua-btn tiny" onclick="App.loadSchemaPreset('product')">Product</button>
                                    <button class="aqua-btn tiny" onclick="App.loadSchemaPreset('summary')">Summary</button>
                                    <button class="aqua-btn tiny" onclick="App.loadSchemaPreset('custom')">Custom</button>
                                </div>
                                <textarea id="chat-json-schema" class="aqua-textarea mono" rows="4" placeholder='{"type": "object", "properties": {...}}'></textarea>
                            </div>
                        </div>
                        
                        <!-- Messages Area -->
                        <div class="chat-messages" id="chat-messages">
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
                        </div>
                        
                        <!-- Typing Indicator -->
                        <div class="chat-typing-indicator" id="chat-typing" style="display: none;">
                            <div class="typing-dots">
                                <span></span><span></span><span></span>
                            </div>
                            <span class="typing-text">AI is thinking...</span>
                            <button class="aqua-btn tiny danger" id="chat-stop-btn" onclick="App.stopGeneration()">Stop</button>
                        </div>
                        
                        <!-- Chat Stats Bar -->
                        <div class="chat-stats-bar" id="chat-stats">
                            <span id="chat-token-count">Tokens: --</span>
                            <span id="chat-response-time">Response: --</span>
                            <span id="chat-message-count">Messages: 0</span>
                        </div>
                        
                        <!-- Input Area -->
                        <div class="chat-input-area">
                            <!-- Smart Suggestions -->
                            <div class="chat-suggestions" id="chat-suggestions" style="display: none;">
                                <span class="suggestions-label">💡 Suggestions:</span>
                                <div class="suggestions-list" id="suggestions-list">
                                    <!-- Suggestions will be added dynamically -->
                                </div>
                            </div>
                            
                            <!-- Image Preview Area -->
                            <div class="chat-image-preview" id="chat-image-preview" style="display: none;">
                                <!-- Images will be added here -->
                            </div>
                            
                            <div class="chat-input-container">
                                <div class="chat-input-actions">
                                    <button class="chat-action-btn" onclick="App.triggerImageUpload()" title="Attach Image (Vision)">
                                        🖼️
                                    </button>
                                    <button class="chat-action-btn" id="voice-input-btn" onclick="App.toggleVoiceInput()" title="Voice Input">
                                        🎤
                                    </button>
                                    <input type="file" id="chat-image-input" accept="image/*" multiple style="display: none;">
                                </div>
                                <textarea id="chat-input" class="chat-input" placeholder="Type a message... (Enter to send, Shift+Enter for new line)" rows="1"></textarea>
                                <button id="chat-send-btn" class="chat-send-btn" title="Send (Enter)">
                                    ➤
                                </button>
                            </div>
                            <div class="chat-input-hints">
                                <span>⌘+Enter to send</span>
                                <span>Drag & drop images</span>
                                <span>🎤 Voice input available</span>
                            </div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== GENERATE WINDOW ==================== -->
        <div class="window" id="window-generate" style="width: 750px; height: 600px; top: 90px; left: 200px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">✨ Generate</div>
            </div>
            <div class="window-content">
                <div class="generate-container">
                    <div class="generate-form">
                        <div class="generate-field">
                            <label>Model</label>
                            <select id="generate-model-select" class="aqua-select model-selector">
                                <option value="">Select a model...</option>
                            </select>
                        </div>
                        
                        <div class="generate-field">
                            <label>System Prompt (Optional)</label>
                            <input type="text" id="generate-system" class="aqua-input" placeholder="You are a helpful assistant...">
                        </div>
                        
                        <div class="generate-field">
                            <label>Prompt</label>
                            <textarea id="generate-prompt" class="aqua-textarea" rows="4" placeholder="Enter your prompt here..."></textarea>
                        </div>
                        
                        <div class="generate-options">
                            <div class="generate-option">
                                <label>Temperature: <span id="temp-value">0.7</span></label>
                                <input type="range" id="generate-temperature" class="aqua-slider" min="0" max="2" step="0.1" value="0.7" oninput="$('#temp-value').text(this.value)">
                            </div>
                        </div>
                        
                        <div>
                            <button class="aqua-btn primary" onclick="App.submitGenerate()">
                                ✨ Generate Response
                            </button>
                        </div>
                    </div>
                    
                    <div class="generate-stats" id="generate-stats"></div>
                    
                    <div class="generate-result empty" id="generate-result">
                        <span>Generated response will appear here...</span>
                    </div>
                </div>
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

        <!-- ==================== EMBEDDINGS WINDOW ==================== -->
        <div class="window" id="window-embeddings" style="width: 700px; height: 550px; top: 110px; left: 250px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">🎯 Embeddings Playground</div>
            </div>
            <div class="window-content">
                <div class="embed-container">
                    <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                        <select id="embed-model-select" class="aqua-select model-selector" style="width: 200px;">
                            <option value="">Select a model...</option>
                        </select>
                        <input type="text" id="embed-input" class="aqua-input" style="flex: 1;" placeholder="Enter text to generate embeddings...">
                        <button class="aqua-btn primary" onclick="App.generateEmbedding()">Generate</button>
                    </div>
                    
                    <div class="embed-visualization" id="embed-visualization">
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(255,255,255,0.5);">
                            Enter text and click Generate to visualize embeddings
                        </div>
                    </div>
                    
                    <div class="embed-stats">
                        <div class="embed-stat">
                            <div class="embed-stat-value" id="embed-stat-dims">--</div>
                            <div class="embed-stat-label">Dimensions</div>
                        </div>
                        <div class="embed-stat">
                            <div class="embed-stat-value" id="embed-stat-min">--</div>
                            <div class="embed-stat-label">Min Value</div>
                        </div>
                        <div class="embed-stat">
                            <div class="embed-stat-value" id="embed-stat-max">--</div>
                            <div class="embed-stat-label">Max Value</div>
                        </div>
                        <div class="embed-stat">
                            <div class="embed-stat-value" id="embed-stat-mean">--</div>
                            <div class="embed-stat-label">Mean</div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== LOGS WINDOW ==================== -->
        <div class="window" id="window-logs" style="width: 850px; height: 500px; top: 130px; left: 300px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">📋 API Logs</div>
            </div>
            <div class="window-content">
                <div class="logs-container">
                    <div class="logs-toolbar">
                        <button class="aqua-btn" onclick="App.loadLogs()">🔄 Refresh</button>
                        <button class="aqua-btn danger" onclick="App.clearLogs()">🗑️ Clear Logs</button>
                        <span style="margin-left: auto; font-size: 12px; color: var(--text-muted);">
                            Showing last 100 requests
                        </span>
                    </div>
                    <div class="logs-list" id="logs-list">
                        <div class="empty-state">
                            <div class="loading-spinner"></div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== MODEL COMPARISON WINDOW ==================== -->
        <div class="window" id="window-compare" style="width: 1000px; height: 650px; top: 40px; left: 80px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">⚖️ Model Comparison</div>
            </div>
            <div class="window-content" style="padding: 16px;">
                <div style="margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Model A</label>
                            <select id="compare-model-1" class="aqua-select model-selector" style="width: 100%;">
                                <option value="">Select first model...</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Model B</label>
                            <select id="compare-model-2" class="aqua-select model-selector" style="width: 100%;">
                                <option value="">Select second model...</option>
                            </select>
                        </div>
                    </div>
                    <textarea id="compare-prompt" class="aqua-textarea" style="width: 100%; height: 60px;" placeholder="Enter a prompt to compare both models..."></textarea>
                    <div style="margin-top: 8px;">
                        <button id="compare-run-btn" class="aqua-btn primary" onclick="App.runComparison()" disabled>
                            ⚖️ Run Comparison
                        </button>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; height: calc(100% - 180px);">
                    <div class="aqua-card" style="display: flex; flex-direction: column;">
                        <div class="aqua-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Model A Response</span>
                            <span id="compare-stats-1" style="font-size: 10px; color: var(--text-muted);"></span>
                        </div>
                        <div class="aqua-card-body" id="compare-result-1" style="flex: 1; overflow-y: auto; font-size: 13px; line-height: 1.5;">
                            <div class="empty-state">
                                <p class="text-muted">Response will appear here</p>
                            </div>
                        </div>
                    </div>
                    <div class="aqua-card" style="display: flex; flex-direction: column;">
                        <div class="aqua-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Model B Response</span>
                            <span id="compare-stats-2" style="font-size: 10px; color: var(--text-muted);"></span>
                        </div>
                        <div class="aqua-card-body" id="compare-result-2" style="flex: 1; overflow-y: auto; font-size: 13px; line-height: 1.5;">
                            <div class="empty-state">
                                <p class="text-muted">Response will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== TERMINAL WINDOW ==================== -->
        <div class="window" id="window-terminal" style="width: 700px; height: 500px; top: 100px; left: 180px;">
            <div class="window-titlebar" style="background: linear-gradient(180deg, #333 0%, #222 100%);">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title" style="color: #CCC;">🖥️ Terminal</div>
            </div>
            <div class="window-content" style="background: #1A1A1A; padding: 0; display: flex; flex-direction: column;">
                <div id="terminal-output" style="flex: 1; overflow-y: auto; padding: 12px; font-family: var(--font-mono); font-size: 12px; color: #0F0;">
                    <div class="terminal-line">Welcome to Ollama Manager Terminal</div>
                    <div class="terminal-line">Type 'help' for available commands</div>
                    <div class="terminal-line">&nbsp;</div>
                </div>
                <div style="display: flex; align-items: center; padding: 8px 12px; background: #252525; border-top: 1px solid #333;">
                    <span style="color: #0F0; font-family: var(--font-mono); margin-right: 8px;">$</span>
                    <input type="text" id="terminal-input" 
                           style="flex: 1; background: transparent; border: none; color: #0F0; font-family: var(--font-mono); font-size: 13px; outline: none;"
                           placeholder="Enter command..." autocomplete="off">
                </div>
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

        <!-- ==================== SETTINGS WINDOW ==================== -->
        <div class="window" id="window-settings" style="width: 700px; height: 550px; top: 60px; left: 120px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">⚙️ Settings</div>
            </div>
            <div class="window-content">
                <div class="settings-container">
                    <div class="settings-sidebar">
                        <div class="settings-nav-items">
                            <div class="settings-nav-item active" data-section="general" onclick="App.scrollToSettingsSection('general')">
                                <span class="settings-nav-icon">⚙️</span>
                                <span>General</span>
                            </div>
                            <div class="settings-nav-item" data-section="server" onclick="App.scrollToSettingsSection('server')">
                                <span class="settings-nav-icon">🖥️</span>
                                <span>Server</span>
                            </div>
                            <div class="settings-nav-item" data-section="models" onclick="App.scrollToSettingsSection('models')">
                                <span class="settings-nav-icon">🤖</span>
                                <span>Models</span>
                            </div>
                            <div class="settings-nav-item" data-section="about" onclick="App.scrollToSettingsSection('about')">
                                <span class="settings-nav-icon">ℹ️</span>
                                <span>About</span>
                            </div>
                        </div>
                        <div class="settings-sidebar-footer">
                            <button class="aqua-btn primary settings-save-btn" onclick="App.saveSettingsForm()">
                                💾 Save Settings
                            </button>
                        </div>
                    </div>
                    <div class="settings-content">
                        <div class="settings-section" id="settings-section-general">
                            <h2>General Settings</h2>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Theme</span>
                                    <small>Choose your preferred color theme</small>
                                </div>
                                <div class="settings-control">
                                    <select id="setting-theme" class="aqua-select">
                                        <option value="aqua">Aqua (Light)</option>
                                        <option value="dark">Dark</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Default Model</span>
                                    <small>Model to use by default for chat</small>
                                </div>
                                <div class="settings-control">
                                    <select id="setting-default-model" class="aqua-select model-selector">
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Auto-refresh Interval</span>
                                    <small>How often to refresh server status (seconds)</small>
                                </div>
                                <div class="settings-control">
                                    <input type="number" id="setting-auto-refresh" class="aqua-input" value="30" min="5" max="300" style="width: 80px;">
                                </div>
                            </div>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Show Notifications</span>
                                    <small>Display notification popups</small>
                                </div>
                                <div class="settings-control">
                                    <label class="aqua-checkbox">
                                        <input type="checkbox" id="setting-notifications" checked>
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section" id="settings-section-server">
                            <h2>Server Settings</h2>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Ollama Host</span>
                                    <small>IP address or hostname of your Ollama server</small>
                                </div>
                                <div class="settings-control">
                                    <input type="text" id="setting-ollama-host" class="aqua-input" value="192.168.1.134" placeholder="192.168.1.134" style="width: 150px;">
                                </div>
                            </div>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Ollama Port</span>
                                    <small>Port number for Ollama server (default: 11434)</small>
                                </div>
                                <div class="settings-control">
                                    <input type="number" id="setting-ollama-port" class="aqua-input" value="11434" min="1" max="65535" style="width: 80px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section" id="settings-section-models">
                            <h2>Model Settings</h2>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Default Temperature</span>
                                    <small>Creativity level (0 = deterministic, 2 = very creative)</small>
                                </div>
                                <div class="settings-control">
                                    <input type="number" id="setting-temperature" class="aqua-input" value="0.7" min="0" max="2" step="0.1" style="width: 80px;">
                                </div>
                            </div>
                            
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Max Tokens</span>
                                    <small>Maximum tokens for generation</small>
                                </div>
                                <div class="settings-control">
                                    <input type="number" id="setting-max-tokens" class="aqua-input" value="8192" min="1" max="128000" style="width: 100px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section" id="settings-section-about">
                            <h2>About</h2>
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Ollama Manager</span>
                                    <small>Version 1.0.0</small>
                                </div>
                                <div class="settings-control">
                                    <span style="font-size: 12px; color: var(--text-muted);">🦙</span>
                                </div>
                            </div>
                            <div class="settings-row">
                                <div class="settings-label">
                                    <span>Keyboard Shortcuts</span>
                                    <small>Ctrl+K: Spotlight, Ctrl+H: Toggle History</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

        <!-- ==================== MODEL CREATOR WINDOW ==================== -->
        <div class="window" id="window-modelcreator" style="width: 1100px; height: 750px; top: 40px; left: 100px;">
            <div class="window-titlebar">
                <div class="traffic-lights">
                    <div class="traffic-light close" title="Close"></div>
                    <div class="traffic-light minimize" title="Minimize"></div>
                    <div class="traffic-light maximize" title="Maximize"></div>
                </div>
                <div class="window-title">🛠️ Model Creator</div>
            </div>
            <div class="window-toolbar">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-size: 12px; font-weight: 500;">Source Model:</label>
                        <select id="creator-source-model" class="aqua-select model-selector" style="width: 200px;">
                            <option value="">Select base model...</option>
                        </select>
                        <button class="aqua-btn small" onclick="App.loadSourceModelForCreator()" title="Load parameters from selected model">
                            📥 Load
                        </button>
                    </div>
                    <div class="toolbar-separator" style="width: 1px; height: 24px; background: var(--border-color);"></div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-size: 12px; font-weight: 500;">New Model Name:</label>
                        <input type="text" id="creator-new-name" class="aqua-input" style="width: 200px;" placeholder="my-custom-model">
                    </div>
                </div>
            </div>
            <div class="window-content" style="display: flex; flex-direction: column; overflow: hidden;">
                <!-- Template Quick Actions -->
                <div class="creator-templates" style="padding: 12px 16px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
                    <span style="font-size: 11px; color: var(--text-muted); margin-right: 12px;">⚡ Quick Templates:</span>
                    <button class="aqua-btn tiny" onclick="App.applyParameterTemplate('creative')" title="High creativity settings">🎨 Creative</button>
                    <button class="aqua-btn tiny" onclick="App.applyParameterTemplate('code')" title="Precise code generation">💻 Code</button>
                    <button class="aqua-btn tiny" onclick="App.applyParameterTemplate('precise')" title="Deterministic, precise answers">🎯 Precise</button>
                    <button class="aqua-btn tiny" onclick="App.applyParameterTemplate('balanced')" title="Default balanced settings">⚖️ Balanced</button>
                    <span style="margin-left: auto; display: flex; gap: 8px;">
                        <button class="aqua-btn tiny" onclick="App.copyAllSourceParams()" title="Copy all parameters from source">📋 Copy Source</button>
                        <button class="aqua-btn tiny" onclick="App.resetCreatorToDefaults()" title="Reset to Ollama defaults">🔄 Reset</button>
                        <button class="aqua-btn tiny" onclick="App.exportCreatorParams()" title="Export configuration">📤 Export</button>
                        <button class="aqua-btn tiny" onclick="App.importCreatorParams()" title="Import configuration">📥 Import</button>
                    </span>
                </div>
                
                <!-- Main Content Area -->
                <div class="creator-content" style="flex: 1; display: flex; overflow: hidden;">
                    <!-- Source Parameters (Read-only) -->
                    <div class="creator-panel source-panel" style="width: 35%; display: flex; flex-direction: column;">
                        <div class="creator-panel-header" style="padding: 12px 16px; background: var(--bg-tertiary); border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 13px; font-weight: 600; margin: 0;">📖 Source Model Parameters</h3>
                            <span id="creator-source-name" style="font-size: 11px; color: var(--text-muted);">No model loaded</span>
                        </div>
                        <div class="creator-panel-content" id="creator-source-params" style="flex: 1; overflow-y: auto; padding: 12px;">
                            <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                                <div style="font-size: 40px; margin-bottom: 12px;">📦</div>
                                <div style="font-size: 13px; color: var(--text-muted);">Select a source model and click Load to view its parameters</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resizable Divider -->
                    <div id="creator-panel-divider" class="creator-panel-divider" title="Drag to resize panels"></div>
                    
                    <!-- New Model Parameters (Editable) -->
                    <div class="creator-panel edit-panel" style="flex: 1; display: flex; flex-direction: column;">
                        <div class="creator-panel-header" style="padding: 12px 16px; background: var(--bg-tertiary); border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 13px; font-weight: 600; margin: 0;">✏️ New Model Configuration</h3>
                            <span style="font-size: 11px; color: var(--text-muted);">Modify parameters for your new model</span>
                        </div>
                        <div class="creator-panel-content" style="flex: 1; overflow-y: auto; padding: 16px;">
                            <!-- System Prompt Section -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>📝 System Prompt</h4>
                                    <span class="creator-hint">Define the AI's personality and behavior</span>
                                </div>
                                <textarea id="creator-system-prompt" class="aqua-textarea" rows="3" placeholder="You are a helpful AI assistant..."></textarea>
                            </div>
                            
                            <!-- Core Parameters -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>🎛️ Core Parameters</h4>
                                </div>
                                <div class="creator-params-grid">
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">temperature</span>
                                            <span class="param-hint" title="Controls randomness. Higher = more creative (0-2)">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-temperature" class="aqua-input" step="0.1" min="0" max="2" placeholder="0.8">
                                        <span class="param-diff" id="diff-temperature"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">num_ctx</span>
                                            <span class="param-hint" title="Context window size in tokens">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-num_ctx" class="aqua-input" min="512" max="131072" placeholder="2048">
                                        <span class="param-diff" id="diff-num_ctx"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">num_predict</span>
                                            <span class="param-hint" title="Max tokens to generate. -1 for unlimited">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-num_predict" class="aqua-input" min="-1" max="128000" placeholder="-1">
                                        <span class="param-diff" id="diff-num_predict"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">seed</span>
                                            <span class="param-hint" title="Random seed for reproducibility. 0 = random">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-seed" class="aqua-input" min="0" placeholder="0">
                                        <span class="param-diff" id="diff-seed"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sampling Parameters -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>🎲 Sampling Parameters</h4>
                                </div>
                                <div class="creator-params-grid">
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">top_k</span>
                                            <span class="param-hint" title="Limits token selection to top K options">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-top_k" class="aqua-input" min="1" max="100" placeholder="40">
                                        <span class="param-diff" id="diff-top_k"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">top_p</span>
                                            <span class="param-hint" title="Nucleus sampling probability (0-1)">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-top_p" class="aqua-input" step="0.05" min="0" max="1" placeholder="0.9">
                                        <span class="param-diff" id="diff-top_p"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">min_p</span>
                                            <span class="param-hint" title="Minimum probability threshold (0-1)">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-min_p" class="aqua-input" step="0.01" min="0" max="1" placeholder="0.0">
                                        <span class="param-diff" id="diff-min_p"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">typical_p</span>
                                            <span class="param-hint" title="Typical sampling probability">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-typical_p" class="aqua-input" step="0.05" min="0" max="1" placeholder="1.0">
                                        <span class="param-diff" id="diff-typical_p"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Repetition Control -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>🔄 Repetition Control</h4>
                                </div>
                                <div class="creator-params-grid">
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">repeat_penalty</span>
                                            <span class="param-hint" title="Penalty for repeating tokens (1.0 = no penalty)">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-repeat_penalty" class="aqua-input" step="0.1" min="0" max="2" placeholder="1.1">
                                        <span class="param-diff" id="diff-repeat_penalty"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">repeat_last_n</span>
                                            <span class="param-hint" title="How far back to look for repetitions. 0 = disabled">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-repeat_last_n" class="aqua-input" min="-1" max="1024" placeholder="64">
                                        <span class="param-diff" id="diff-repeat_last_n"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">presence_penalty</span>
                                            <span class="param-hint" title="Penalty for tokens already in context">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-presence_penalty" class="aqua-input" step="0.1" min="-2" max="2" placeholder="0.0">
                                        <span class="param-diff" id="diff-presence_penalty"></span>
                                    </div>
                                    <div class="creator-param">
                                        <label>
                                            <span class="param-name">frequency_penalty</span>
                                            <span class="param-hint" title="Penalty based on token frequency">ℹ️</span>
                                        </label>
                                        <input type="number" id="creator-frequency_penalty" class="aqua-input" step="0.1" min="-2" max="2" placeholder="0.0">
                                        <span class="param-diff" id="diff-frequency_penalty"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stop Sequences -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>🛑 Stop Sequences</h4>
                                    <button class="aqua-btn tiny" onclick="App.addStopSequence()" style="margin-left: auto;">+ Add</button>
                                </div>
                                <div id="creator-stop-sequences" class="creator-stop-list">
                                    <div class="creator-stop-item">
                                        <input type="text" class="aqua-input stop-input" placeholder="Enter stop sequence...">
                                        <button class="aqua-btn tiny danger" onclick="App.removeStopSequence(this)">✕</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Prompt Template -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>📝 Prompt Template</h4>
                                    <span class="creator-hint">Define how prompts are formatted</span>
                                </div>
                                <textarea id="creator-template" class="aqua-textarea mono" rows="4" placeholder="Leave empty to use source model's template..."></textarea>
                                <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                    Template variables: {{ .System }}, {{ .Prompt }}, {{ .Response }}
                                </p>
                            </div>
                            
                            <!-- Message Examples -->
                            <div class="creator-section">
                                <div class="creator-section-header">
                                    <h4>💬 Message Examples</h4>
                                    <span class="creator-hint">Define example conversations for fine-tuning</span>
                                </div>
                                <textarea id="creator-message" class="aqua-textarea mono" rows="6" placeholder="Example conversation format:
user: Hello, how are you?
assistant: I'm doing well, thank you for asking! How can I help you today?

user: Can you explain quantum physics?
assistant: Quantum physics is a fundamental theory in physics that describes nature at the smallest scales..."></textarea>
                                <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                    Use "user:", "assistant:", or "system:" to define conversation examples
                                </p>
                            </div>
                            
                            <!-- Action Buttons - Moved into main content area -->
                            <div class="creator-section creator-actions-section" style="margin-top: 20px; padding: 16px; background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary)); border-radius: 8px; border: 1px solid var(--border-color);">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                    <div id="creator-status" style="font-size: 12px; color: var(--text-muted);">
                                        Ready to create a new model
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button class="aqua-btn" onclick="App.previewModelCreation()" title="Preview the model configuration before creation">
                                            👁️ Preview
                                        </button>
                                        <button class="aqua-btn primary" id="creator-create-btn" onclick="App.createNewModel()" title="Create the new model">
                                            🚀 Create Model
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Validation Messages -->
                <div id="creator-validation" class="creator-validation" style="display: none;">
                    <!-- Validation messages will appear here -->
                </div>
            </div>
            
            <!-- Footer - Status only now -->
            <div class="window-footer" style="display: flex; justify-content: center; align-items: center; padding: 8px 16px;">
                <span style="font-size: 11px; color: var(--text-muted);">💡 Tip: Click on any parameter in Source Model to copy it to New Model</span>
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

    <!-- Dock -->
    <div class="dock">
        <div class="dock-item" data-window="dashboard" title="Dashboard">
            <div class="dock-tooltip">Dashboard</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad1)"/>
                <rect x="20" y="25" width="25" height="15" rx="3" fill="rgba(255,255,255,0.9)"/>
                <rect x="55" y="25" width="25" height="15" rx="3" fill="rgba(255,255,255,0.9)"/>
                <rect x="20" y="50" width="60" height="8" rx="2" fill="rgba(255,255,255,0.7)"/>
                <rect x="20" y="65" width="40" height="8" rx="2" fill="rgba(255,255,255,0.7)"/>
                <defs>
                    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#667eea"/>
                        <stop offset="100%" style="stop-color:#764ba2"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="models" title="Model Manager">
            <div class="dock-tooltip">Models</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad2)"/>
                <rect x="22" y="30" width="56" height="12" rx="3" fill="rgba(255,255,255,0.9)"/>
                <rect x="22" y="48" width="56" height="12" rx="3" fill="rgba(255,255,255,0.7)"/>
                <rect x="22" y="66" width="40" height="12" rx="3" fill="rgba(255,255,255,0.5)"/>
                <defs>
                    <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#6366F1"/>
                        <stop offset="100%" style="stop-color:#8B5CF6"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="modelcreator" title="Model Creator">
            <div class="dock-tooltip">Model Creator</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#gradCreator)"/>
                <circle cx="50" cy="45" r="18" fill="rgba(255,255,255,0.9)"/>
                <rect x="47" y="27" width="6" height="36" rx="2" fill="#14B8A6"/>
                <rect x="32" y="42" width="36" height="6" rx="2" fill="#14B8A6"/>
                <rect x="30" y="68" width="40" height="6" rx="2" fill="rgba(255,255,255,0.7)"/>
                <defs>
                    <linearGradient id="gradCreator" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#14B8A6"/>
                        <stop offset="100%" style="stop-color:#0D9488"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="chat" title="Chat">
            <div class="dock-tooltip">Chat</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad3)"/>
                <ellipse cx="50" cy="50" rx="30" ry="25" fill="rgba(255,255,255,0.9)"/>
                <circle cx="38" cy="50" r="4" fill="#0066CC"/>
                <circle cx="50" cy="50" r="4" fill="#0066CC"/>
                <circle cx="62" cy="50" r="4" fill="#0066CC"/>
                <defs>
                    <linearGradient id="grad3" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#0066CC"/>
                        <stop offset="100%" style="stop-color:#3399FF"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="generate" title="Generate">
            <div class="dock-tooltip">Generate</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad4)"/>
                <polygon points="50,25 70,70 30,70" fill="rgba(255,255,255,0.9)"/>
                <circle cx="50" cy="55" r="8" fill="#F59E0B"/>
                <defs>
                    <linearGradient id="grad4" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#F59E0B"/>
                        <stop offset="100%" style="stop-color:#EF4444"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-separator"></div>
        <div class="dock-item" data-window="embeddings" title="Embeddings">
            <div class="dock-tooltip">Embeddings</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad5)"/>
                <circle cx="35" cy="40" r="8" fill="rgba(255,255,255,0.9)"/>
                <circle cx="65" cy="40" r="8" fill="rgba(255,255,255,0.7)"/>
                <circle cx="50" cy="65" r="8" fill="rgba(255,255,255,0.5)"/>
                <line x1="35" y1="40" x2="65" y2="40" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <line x1="35" y1="40" x2="50" y2="65" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <line x1="65" y1="40" x2="50" y2="65" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <defs>
                    <linearGradient id="grad5" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#10B981"/>
                        <stop offset="100%" style="stop-color:#059669"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="logs" title="API Logs">
            <div class="dock-tooltip">Logs</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad6)"/>
                <rect x="22" y="28" width="56" height="6" rx="2" fill="rgba(255,255,255,0.9)"/>
                <rect x="22" y="40" width="45" height="6" rx="2" fill="rgba(255,255,255,0.7)"/>
                <rect x="22" y="52" width="56" height="6" rx="2" fill="rgba(255,255,255,0.6)"/>
                <rect x="22" y="64" width="35" height="6" rx="2" fill="rgba(255,255,255,0.5)"/>
                <defs>
                    <linearGradient id="grad6" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#6B7280"/>
                        <stop offset="100%" style="stop-color:#4B5563"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-separator"></div>
        <div class="dock-item" data-window="compare" title="Model Comparison">
            <div class="dock-tooltip">Compare</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad8)"/>
                <rect x="20" y="30" width="25" height="40" rx="4" fill="rgba(255,255,255,0.9)"/>
                <rect x="55" y="30" width="25" height="40" rx="4" fill="rgba(255,255,255,0.7)"/>
                <path d="M45 50 L55 50 M50 45 L55 50 L50 55" stroke="rgba(255,255,255,0.9)" stroke-width="2" fill="none"/>
                <defs>
                    <linearGradient id="grad8" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#EC4899"/>
                        <stop offset="100%" style="stop-color:#8B5CF6"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="terminal" title="Terminal">
            <div class="dock-tooltip">Terminal</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="#1A1A1A"/>
                <path d="M25 40 L40 50 L25 60" stroke="#0F0" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="45" y="58" width="30" height="4" rx="2" fill="#0F0"/>
                <defs>
                    <linearGradient id="grad9" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#1A1A1A"/>
                        <stop offset="100%" style="stop-color:#333"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="dock-item" data-window="settings" title="Settings">
            <div class="dock-tooltip">Settings</div>
            <svg viewBox="0 0 100 100">
                <rect x="10" y="10" width="80" height="80" rx="15" fill="url(#grad7)"/>
                <circle cx="50" cy="50" r="20" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="6"/>
                <circle cx="50" cy="50" r="8" fill="rgba(255,255,255,0.9)"/>
                <rect x="47" y="15" width="6" height="12" rx="2" fill="rgba(255,255,255,0.9)"/>
                <rect x="47" y="73" width="6" height="12" rx="2" fill="rgba(255,255,255,0.9)"/>
                <rect x="15" y="47" width="12" height="6" rx="2" fill="rgba(255,255,255,0.9)"/>
                <rect x="73" y="47" width="12" height="6" rx="2" fill="rgba(255,255,255,0.9)"/>
                <defs>
                    <linearGradient id="grad7" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#9CA3AF"/>
                        <stop offset="100%" style="stop-color:#6B7280"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
    </div>

    <!-- Spotlight Search Overlay -->
    <div class="spotlight-overlay" id="spotlight-overlay">
        <div class="spotlight-container">
            <div class="spotlight-input-container">
                <span class="spotlight-icon">🔍</span>
                <input type="text" id="spotlight-input" class="spotlight-input" placeholder="Search models, commands, or actions...">
            </div>
            <div class="spotlight-results" id="spotlight-results">
                <!-- Results will be populated by JS -->
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div class="notification-container" id="notification-container">
        <!-- Notifications will be added here -->
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Marked.js for Markdown parsing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.0/marked.min.js"></script>
    
    <!-- Highlight.js for syntax highlighting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
    <!-- Application JavaScript -->
    <script src="assets/js/app.js"></script>
    
    <script>
        // Hide loading screen when ready
        $(document).ready(function() {
            setTimeout(function() {
                $('#app-loading').addClass('hidden');
                setTimeout(function() {
                    $('#app-loading').remove();
                }, 500);
            }, 800);
        });
        
        // Update clock in menubar
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            $('#menubar-time').text(time);
        }
        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>
