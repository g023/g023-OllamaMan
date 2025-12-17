# Ollama Manager Updates

## December 17, 2025 - UI/UX Improvements & Dark Mode Overhaul

### Chat History Sidebar
- **Collapsible Sidebar**: Chat history sidebar is now hidden by default and can be toggled via the "📋 History" button
- **Resizable Width**: Drag the divider between history and chat to resize (200-450px range)
- **Persistent State**: Collapsed state and width are saved to localStorage
- **Keyboard Shortcut**: Use `Ctrl+H` to quickly toggle the history sidebar
- **Text Truncation**: History previews are now limited to 35 characters to prevent overflow issues with the delete button

### Chat Defaults Updated
- Default Max Tokens increased from 2048 to **8192**
- Default Context Size increased from 4096 to **16384**

### Welcome Screen Behavior
- Welcome screen with llama icon now properly hides when chat begins
- Welcome screen reappears when chat is cleared

### Options Panel Layout Fix
- Fixed overlapping of input fields with temperature slider at narrow widths
- Improved responsive grid with better minimum widths (180px vs 150px)

### Settings Window Overhaul
- Sidebar navigation now scrolls to corresponding sections smoothly
- Save Settings button moved to sidebar footer for always-visible access
- Added About section with version info and keyboard shortcuts reference
- Improved visual hierarchy and spacing

### Dark Theme Comprehensive Improvements
- **Form Inputs**: Better contrast, visible borders (#4D4D5D), and clear focus states
- **Window Chrome**: Improved titlebar gradients and text contrast
- **Buttons**: Better gradient styling and hover states
- **Chat Messages**: Improved assistant bubble readability
- **Scrollbars**: Dark mode appropriate colors
- **Notifications**: Better readability with proper background and border
- **Badges**: Proper dark mode color schemes for success/warning/danger/info
- **Model Lists & History Items**: Better hover and selected states
- **Empty States**: Improved visibility of placeholder text
- **Spotlight Search**: Dark theme styling

---

## Previous Updates

- Extended time limits for message timeouts
- Fixed stop button for streaming chat responses (AbortController implementation)
