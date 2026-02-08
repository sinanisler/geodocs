/**
 * GEODocs Admin Script
 * 
 * Handles admin interface interactions for settings page
 * 
 * @package GEODocs
 * @version 0.1
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        initApiKeyTest();
        initModelLoader();
    }
    
    /**
     * Initialize API key testing
     */
    function initApiKeyTest() {
        const testButton = document.getElementById('test-api-key');
        if (!testButton) return;
        
        testButton.addEventListener('click', async function() {
            const resultSpan = document.getElementById('api-test-result');
            const originalText = this.textContent;
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            resultSpan.innerHTML = '';
            
            try {
                const response = await fetch(geodocs.restUrl + 'models', {
                    headers: {
                        'X-WP-Nonce': geodocs.nonce
                    }
                });
                
                const data = await response.json();
                
                if (data.code) {
                    // Error
                    resultSpan.innerHTML = '<span class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm"><i class="fas fa-times-circle"></i> Failed: ' + escapeHtml(data.message) + '</span>';
                } else {
                    // Success
                    resultSpan.innerHTML = '<span class="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm"><i class="fas fa-check-circle"></i> API Key is valid! Found ' + data.length + ' models.</span>';
                }
            } catch (error) {
                resultSpan.innerHTML = '<span class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm"><i class="fas fa-exclamation-triangle"></i> Error: ' + escapeHtml(error.message) + '</span>';
            } finally {
                // Restore button
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check-circle"></i> ' + originalText.replace(/^.*?\s/, '');
            }
        });
    }
    
    /**
     * Initialize model loader
     */
    function initModelLoader() {
        const loadButton = document.getElementById('load-models');
        if (!loadButton) return;
        
        loadButton.addEventListener('click', async function() {
            const modelSelect = document.getElementById('model');
            const modelsList = document.getElementById('models-list');
            const originalText = this.textContent;
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            modelsList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-slate-600">Loading models...</p></div>';
            modelsList.classList.remove('hidden');
            
            try {
                const response = await fetch(geodocs.restUrl + 'models', {
                    headers: {
                        'X-WP-Nonce': geodocs.nonce
                    }
                });
                
                const models = await response.json();
                
                if (models.code) {
                    // Error
                    alert('Error: ' + models.message);
                    modelsList.classList.add('hidden');
                    return;
                }
                
                // Get current selected model
                const currentModel = modelSelect.value;
                
                // Clear and populate select
                modelSelect.innerHTML = '';
                
                // Build models grid HTML
                let html = '<div class="bg-slate-50 rounded-lg p-4"><h4 class="font-semibold text-slate-800 mb-3 flex items-center gap-2"><i class="fas fa-robot text-blue-600"></i> Available Vision Models (' + models.length + ')</h4><div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-96 overflow-y-auto">';
                
                models.forEach(model => {
                    const isSelected = model.id === currentModel;
                    const inputCost = (parseFloat(model.pricing.prompt) * 1000000).toFixed(3);
                    const outputCost = (parseFloat(model.pricing.completion) * 1000000).toFixed(3);
                    const contextK = (model.context_length / 1000).toFixed(0);
                    
                    // Add to select
                    const option = document.createElement('option');
                    option.value = model.id;
                    option.textContent = model.name;
                    if (isSelected) option.selected = true;
                    modelSelect.appendChild(option);
                    
                    // Add to grid
                    html += `
                        <div class="bg-white border-2 ${isSelected ? 'border-blue-500' : 'border-slate-200'} rounded-lg p-4 hover:border-blue-300 transition cursor-pointer model-card" data-model-id="${escapeHtml(model.id)}">
                            <div class="flex items-start justify-between mb-2">
                                <h5 class="font-semibold text-slate-800 text-sm ${isSelected ? 'text-blue-700' : ''}">${escapeHtml(model.name)}</h5>
                                ${isSelected ? '<i class="fas fa-check-circle text-blue-600"></i>' : ''}
                            </div>
                            <p class="text-xs text-slate-500 mb-2 font-mono">${escapeHtml(model.id)}</p>
                            <p class="text-xs text-slate-600 mb-3 line-clamp-2">${escapeHtml(model.description.substring(0, 120))}...</p>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500"><i class="fas fa-layer-group"></i> ${contextK}K</span>
                                <span class="text-green-600 font-semibold">$${inputCost}/$${outputCost}</span>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
                
                modelsList.innerHTML = html;
                
                // Add click handlers to model cards
                document.querySelectorAll('.model-card').forEach(card => {
                    card.addEventListener('click', function() {
                        const modelId = this.dataset.modelId;
                        modelSelect.value = modelId;
                        
                        // Update visual selection
                        document.querySelectorAll('.model-card').forEach(c => {
                            c.classList.remove('border-blue-500');
                            c.classList.add('border-slate-200');
                            c.querySelector('h5')?.classList.remove('text-blue-700');
                            c.querySelector('.fa-check-circle')?.remove();
                        });
                        
                        this.classList.add('border-blue-500');
                        this.classList.remove('border-slate-200');
                        const title = this.querySelector('h5');
                        if (title) title.classList.add('text-blue-700');
                        const titleDiv = this.querySelector('.flex.items-start');
                        if (titleDiv) {
                            titleDiv.innerHTML += '<i class="fas fa-check-circle text-blue-600"></i>';
                        }
                        
                        // Show notification
                        showNotification('Model selected: ' + modelSelect.options[modelSelect.selectedIndex].text, 'success');
                    });
                });
                
            } catch (error) {
                alert('Error loading models: ' + error.message);
                modelsList.classList.add('hidden');
            } finally {
                // Restore button
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-sync-alt"></i> ' + originalText.replace(/^.*?\s/, '');
            }
        });
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500',
            warning: 'bg-yellow-500'
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full`;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>${escapeHtml(message)}`;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})();
