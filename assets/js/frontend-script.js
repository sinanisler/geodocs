/**
 * GEODocs Frontend Script
 * 
 * Handles frontend user interface for document management
 * 
 * @package GEODocs
 * @version 0.1
 */

(function() {
    'use strict';
    
    // ============================================================================
    // GLOBAL STATE
    // ============================================================================
    const AppState = {
        currentUser: null,
        documents: [],
        categories: [],
        currentView: 'dashboard', // dashboard, upload, document-detail
        selectedCategory: null,
        searchQuery: '',
        loading: false,
        selectedDocument: null,
        viewMode: 'grid', // grid or list
        perPage: 12,
        currentPage: 1,
        totalPages: 1,
        showUpload: true,
        showSearch: true,
        showFilters: true,
    };
    
    // ============================================================================
    // INITIALIZATION
    // ============================================================================
    document.addEventListener('DOMContentLoaded', () => {
        const appContainer = document.getElementById('geodocs-app');
        
        if (!appContainer || !window.geodocs) {
            console.error('GEODocs: Container or data not found');
            return;
        }
        
        // Get shortcode attributes
        AppState.viewMode = appContainer.dataset.view || 'grid';
        AppState.perPage = parseInt(appContainer.dataset.perPage) || 12;
        AppState.showUpload = appContainer.dataset.showUpload !== 'false';
        AppState.showSearch = appContainer.dataset.showSearch !== 'false';
        AppState.showFilters = appContainer.dataset.showFilters !== 'false';
        
        // Set initial state
        AppState.currentUser = geodocs.currentUser;
        AppState.categories = geodocs.categories || [];
        
        // Load documents
        loadDocuments();
    });
    
    // ============================================================================
    // API FUNCTIONS
    // ============================================================================
    async function apiRequest(endpoint, options = {}) {
        const url = geodocs.restUrl + endpoint;
        const headers = {
            'X-WP-Nonce': geodocs.nonce,
            ...options.headers,
        };
        
        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        
        const response = await fetch(url, {
            ...options,
            headers,
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Request failed');
        }
        
        return response.json();
    }
    
    async function loadDocuments() {
        try {
            AppState.loading = true;
            renderApp();
            
            let endpoint = 'documents';
            const params = new URLSearchParams();
            
            if (AppState.selectedCategory) {
                params.append('category', AppState.selectedCategory);
            }
            
            if (AppState.searchQuery) {
                params.append('search', AppState.searchQuery);
            }
            
            params.append('per_page', AppState.perPage);
            params.append('page', AppState.currentPage);
            
            if (params.toString()) {
                endpoint += '?' + params.toString();
            }
            
            const result = await apiRequest(endpoint);
            AppState.documents = result.documents || [];
            AppState.totalPages = result.pages || 1;
            
        } catch (error) {
            console.error('Failed to load documents:', error);
            showNotification('Failed to load documents: ' + error.message, 'error');
        } finally {
            AppState.loading = false;
            renderApp();
        }
    }
    
    async function uploadDocument(file) {
        try {
            AppState.loading = true;
            renderApp();
            
            const formData = new FormData();
            formData.append('file', file);
            
            const newDoc = await apiRequest('documents', {
                method: 'POST',
                body: formData,
            });
            
            AppState.documents.unshift(newDoc);
            AppState.currentView = 'dashboard';
            
            showNotification('Document uploaded and analyzed successfully!', 'success');
        } catch (error) {
            console.error('Upload failed:', error);
            showNotification('Upload failed: ' + error.message, 'error');
        } finally {
            AppState.loading = false;
            renderApp();
        }
    }
    
    async function deleteDocument(id) {
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }
        
        try {
            await apiRequest(`documents/${id}`, { method: 'DELETE' });
            AppState.documents = AppState.documents.filter(doc => doc.id !== id);
            
            if (AppState.currentView === 'document-detail' && AppState.selectedDocument?.id === id) {
                AppState.currentView = 'dashboard';
                AppState.selectedDocument = null;
            }
            
            renderApp();
            showNotification('Document deleted successfully', 'success');
        } catch (error) {
            console.error('Delete failed:', error);
            showNotification('Failed to delete document: ' + error.message, 'error');
        }
    }
    
    async function updateDocument(id, data) {
        try {
            const updated = await apiRequest(`documents/${id}`, {
                method: 'PUT',
                body: data,
            });
            
            const index = AppState.documents.findIndex(doc => doc.id === id);
            if (index !== -1) {
                AppState.documents[index] = updated;
            }
            
            if (AppState.selectedDocument?.id === id) {
                AppState.selectedDocument = updated;
            }
            
            renderApp();
            showNotification('Document updated successfully', 'success');
        } catch (error) {
            console.error('Update failed:', error);
            showNotification('Failed to update document: ' + error.message, 'error');
        }
    }
    
    // ============================================================================
    // RENDER FUNCTIONS
    // ============================================================================
    function renderApp() {
        const container = document.getElementById('geodocs-app');
        if (!container) return;
        
        let html = '';
        
        switch (AppState.currentView) {
            case 'dashboard':
                html = getDashboardHTML();
                break;
            case 'upload':
                html = getUploadHTML();
                break;
            case 'document-detail':
                html = getDocumentDetailHTML();
                break;
            default:
                html = getDashboardHTML();
        }
        
        container.innerHTML = html;
        attachEventListeners();
    }
    
    function getDashboardHTML() {
        return `
            <div class="geodocs-dashboard">
                <!-- Header -->
                <div class="geodocs-header">
                    <div class="geodocs-header-content">
                        <div>
                            <h1 class="geodocs-title">
                                <i class="fas fa-folder-open"></i>
                                My Documents
                            </h1>
                            <p class="geodocs-subtitle">${AppState.documents.length} document${AppState.documents.length !== 1 ? 's' : ''}</p>
                        </div>
                        ${AppState.showUpload ? `
<button onclick="window.geodocs.showUpload()" class="geodocs-btn geodocs-btn-primary">
                                <i class="fas fa-upload"></i>
                                Upload Document
                            </button>
                        ` : ''}
                    </div>
                </div>
                
                ${AppState.showSearch || AppState.showFilters ? `
                    <!-- Search & Filter -->
                    <div class="geodocs-filters">
                        ${AppState.showSearch ? `
                            <div class="geodocs-search-wrapper">
                                <i class="fas fa-search geodocs-search-icon"></i>
                                <input 
                                    type="text" 
                                    id="geodocs-search-input"
                                    placeholder="Search documents..." 
                                    value="${escapeHtml(AppState.searchQuery)}"
                                    class="geodocs-search-input"
                                >
                            </div>
                        ` : ''}
                        
                        ${AppState.showFilters ? `
                            <!-- Category Filter -->
                            <div class="geodocs-category-filter">
                                <button 
                                    onclick="window.geodocs.filterByCategory(null)"
                                    class="geodocs-category-chip ${!AppState.selectedCategory ? 'active' : ''}"
                                >
                                    <i class="fas fa-th"></i>
                                    All Categories
                                </button>
                                ${AppState.categories.map(cat => `
                                    <button 
                                        onclick="window.geodocs.filterByCategory(${cat.id})"
                                        class="geodocs-category-chip ${AppState.selectedCategory === cat.id ? 'active' : ''}"
                                    >
                                        <span class="category-icon">${cat.icon}</span>
                                        <span>${escapeHtml(cat.name)}</span>
                                        ${cat.count > 0 ? `<span class="category-count">${cat.count}</span>` : ''}
                                    </button>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
                
                <!-- View Mode Toggle -->
                <div class="geodocs-toolbar">
                    <div class="geodocs-view-toggle">
                        <button 
                            onclick="window.geodocs.setViewMode('grid')"
                            class="geodocs-view-btn ${AppState.viewMode === 'grid' ? 'active' : ''}"
                            title="Grid View"
                        >
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button 
                            onclick="window.geodocs.setViewMode('list')"
                            class="geodocs-view-btn ${AppState.viewMode === 'list' ? 'active' : ''}"
                            title="List View"
                        >
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Documents -->
                ${AppState.loading ? getLoadingHTML() : getDocumentsHTML()}
                
                <!-- Pagination -->
                ${AppState.totalPages > 1 ? getPaginationHTML() : ''}
            </div>
        `;
    }
    
    function getDocumentsHTML() {
        if (AppState.documents.length === 0) {
            return `
                <div class="geodocs-empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No documents found</h3>
                    <p>Upload your first document to get started</p>
                    ${AppState.showUpload ? `
                        <button onclick="window.geodocs.showUpload()" class="geodocs-btn geodocs-btn-primary">
                            <i class="fas fa-upload"></i>
                            Upload Document
                        </button>
                    ` : ''}
                </div>
            `;
        }
        
        if (AppState.viewMode === 'grid') {
            return `
                <div class="geodocs-grid">
                    ${AppState.documents.map(doc => getDocumentCardHTML(doc)).join('')}
                </div>
            `;
        } else {
            return `
                <div class="geodocs-list">
                    ${AppState.documents.map(doc => getDocumentListItemHTML(doc)).join('')}
                </div>
            `;
        }
    }
    
    function getDocumentCardHTML(doc) {
        const category = doc.category || { name: 'Uncategorized', icon: '📁', color: 'bg-gray-500' };
        
        return `
            <div class="geodocs-card" data-doc-id="${doc.id}">
                <div class="geodocs-card-header">
                    <span class="geodocs-badge ${category.color}">
                        <span>${category.icon}</span>
                        <span>${escapeHtml(category.name)}</span>
                    </span>
                    <div class="geodocs-card-actions">
                        <button onclick="window.geodocs.viewDocument(${doc.id})" class="geodocs-icon-btn" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="window.geodocs.editDocument(${doc.id})" class="geodocs-icon-btn" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="window.geodocs.deleteDocument(${doc.id})" class="geodocs-icon-btn geodocs-icon-btn-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <h3 class="geodocs-card-title">${escapeHtml(doc.title)}</h3>
                
                <p class="geodocs-card-description">${escapeHtml(doc.description)}</p>
                
                ${doc.metadata && Object.keys(doc.metadata).length > 0 ? `
                    <div class="geodocs-card-metadata">
                        ${doc.metadata.dates && doc.metadata.dates.length > 0 ? `
                            <div class="geodocs-metadata-item">
                                <i class="fas fa-calendar"></i>
                                <span>${escapeHtml(doc.metadata.dates[0])}</span>
                            </div>
                        ` : ''}
                        ${doc.metadata.amounts && doc.metadata.amounts.length > 0 ? `
                            <div class="geodocs-metadata-item">
                                <i class="fas fa-dollar-sign"></i>
                                <span>${escapeHtml(doc.metadata.amounts.join(', '))}</span>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
                
                <div class="geodocs-card-footer">
                    <span><i class="fas fa-clock"></i> ${formatDate(doc.createdAt)}</span>
                    <span><i class="fas fa-file"></i> ${formatFileSize(doc.fileSize)}</span>
                </div>
            </div>
        `;
    }
    
    function getDocumentListItemHTML(doc) {
        const category = doc.category || { name: 'Uncategorized', icon: '📁', color: 'bg-gray-500' };
        
        return `
            <div class="geodocs-list-item" data-doc-id="${doc.id}">
                <div class="geodocs-list-icon ${category.color}">
                    ${category.icon}
                </div>
                
                <div class="geodocs-list-content">
                    <h3 class="geodocs-list-title">${escapeHtml(doc.title)}</h3>
                    <p class="geodocs-list-description">${escapeHtml(doc.description)}</p>
                    <div class="geodocs-list-meta">
                        <span class="geodocs-badge ${category.color} small">
                            ${escapeHtml(category.name)}
                        </span>
                        <span><i class="fas fa-clock"></i> ${formatDate(doc.createdAt)}</span>
                        <span><i class="fas fa-file"></i> ${formatFileSize(doc.fileSize)}</span>
                    </div>
                </div>
                
                <div class="geodocs-list-actions">
                    <button onclick="window.geodocs.viewDocument(${doc.id})" class="geodocs-btn geodocs-btn-sm">
                        <i class="fas fa-eye"></i>
                        View
                    </button>
                    <button onclick="window.geodocs.editDocument(${doc.id})" class="geodocs-icon-btn" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="window.geodocs.deleteDocument(${doc.id})" class="geodocs-icon-btn geodocs-icon-btn-danger" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }
    
    function getUploadHTML() {
        return `
            <div class="geodocs-upload-page">
                <div class="geodocs-page-header">
                    <button onclick="window.geodocs.showDashboard()" class="geodocs-back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h2 class="geodocs-page-title">Upload Document</h2>
                </div>
                
                ${AppState.loading ? getUploadProgressHTML() : getUploadFormHTML()}
            </div>
        `;
    }
    
    function getUploadFormHTML() {
        return `
            <div 
                id="geodocs-drop-zone" 
                class="geodocs-drop-zone"
            >
                <i class="fas fa-cloud-upload-alt"></i>
                <p class="geodocs-drop-title">Drag & drop your document here</p>
                <p class="geodocs-drop-subtitle">or click to browse</p>
                <input 
                    type="file" 
                    id="geodocs-file-input" 
                    class="geodocs-file-input" 
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"
                >
                <button 
                    onclick="document.getElementById('geodocs-file-input').click()" 
                    class="geodocs-btn geodocs-btn-primary"
                >
                    <i class="fas fa-folder-open"></i>
                    Choose File
                </button>
            </div>
            
            <div class="geodocs-upload-info">
                <p><strong>Supported formats:</strong> PDF, JPG, PNG, GIF, WebP</p>
                <p><strong>Max file size:</strong> ${Math.round(geodocs.maxFileSize / 1024 / 1024)} MB</p>
            </div>
        `;
    }
    
    function getUploadProgressHTML() {
        return `
            <div class="geodocs-upload-progress">
                <i class="fas fa-robot geodocs-ai-icon"></i>
                <h3>Analyzing Document...</h3>
                <p>Our AI is reading and categorizing your document</p>
                <div class="geodocs-progress-bar">
                    <div class="geodocs-progress-fill"></div>
                </div>
                <p class="geodocs-progress-text">This usually takes 5-10 seconds</p>
            </div>
        `;
    }
    
    function getDocumentDetailHTML() {
        if (!AppState.selectedDocument) {
            return `<div class="geodocs-error">Document not found</div>`;
        }
        
        const doc = AppState.selectedDocument;
        const category = doc.category || { name: 'Uncategorized', icon: '📁', color: 'bg-gray-500' };
        
        return `
            <div class="geodocs-document-detail">
                <div class="geodocs-page-header">
                    <button onclick="window.geodocs.showDashboard()" class="geodocs-back-btn">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h2 class="geodocs-page-title">Document Details</h2>
                    <div class="geodocs-detail-actions">
                        <button onclick="window.geodocs.editDocument(${doc.id})" class="geodocs-btn geodocs-btn-secondary">
                            <i class="fas fa-edit"></i>
                            Edit
                        </button>
                        <button onclick="window.geodocs.deleteDocument(${doc.id})" class="geodocs-btn geodocs-btn-danger">
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>
                    </div>
                </div>
                
                <div class="geodocs-detail-content">
                    <div class="geodocs-detail-sidebar">
                        <div class="geodocs-detail-preview">
                            ${doc.fileType.startsWith('image/') ? `
                                <img src="${doc.fileUrl}" alt="${escapeHtml(doc.title)}" class="geodocs-preview-image">
                            ` : `
                                <div class="geodocs-preview-placeholder">
                                    <i class="fas fa-file-pdf"></i>
                                    <p>PDF Document</p>
                                </div>
                            `}
                        </div>
                        <a href="${doc.fileUrl}" target="_blank" class="geodocs-btn geodocs-btn-primary geodocs-btn-block">
                            <i class="fas fa-download"></i>
                            Download File
                        </a>
                    </div>
                    
                    <div class="geodocs-detail-main">
                        <div class="geodocs-detail-section">
                            <span class="geodocs-badge ${category.color}">
                                <span>${category.icon}</span>
                                <span>${escapeHtml(category.name)}</span>
                            </span>
                            <h1 class="geodocs-detail-title">${escapeHtml(doc.title)}</h1>
                            <p class="geodocs-detail-description">${escapeHtml(doc.description)}</p>
                        </div>
                        
                        ${doc.metadata && Object.keys(doc.metadata).length > 0 ? `
                            <div class="geodocs-detail-section">
                                <h3 class="geodocs-section-title">
                                    <i class="fas fa-info-circle"></i>
                                    Extracted Information
                                </h3>
                                <div class="geodocs-metadata-grid">
                                    ${doc.metadata.dates && doc.metadata.dates.length > 0 ? `
                                        <div class="geodocs-metadata-group">
                                            <strong><i class="fas fa-calendar"></i> Dates</strong>
                                            <ul>
                                                ${doc.metadata.dates.map(date => `<li>${escapeHtml(date)}</li>`).join('')}
                                            </ul>
                                        </div>
                                    ` : ''}
                                    ${doc.metadata.amounts && doc.metadata.amounts.length > 0 ? `
                                        <div class="geodocs-metadata-group">
                                            <strong><i class="fas fa-dollar-sign"></i> Amounts</strong>
                                            <ul>
                                                ${doc.metadata.amounts.map(amount => `<li>${escapeHtml(amount)}</li>`).join('')}
                                            </ul>
                                        </div>
                                    ` : ''}
                                    ${doc.metadata.entities && doc.metadata.entities.length > 0 ? `
                                        <div class="geodocs-metadata-group">
                                            <strong><i class="fas fa-building"></i> Entities</strong>
                                            <ul>
                                                ${doc.metadata.entities.map(entity => `<li>${escapeHtml(entity)}</li>`).join('')}
                                            </ul>
                                        </div>
                                    ` : ''}
                                    ${doc.metadata.document_numbers && doc.metadata.document_numbers.length > 0 ? `
                                        <div class="geodocs-metadata-group">
                                            <strong><i class="fas fa-hashtag"></i> Document Numbers</strong>
                                            <ul>
                                                ${doc.metadata.document_numbers.map(num => `<li>${escapeHtml(num)}</li>`).join('')}
                                            </ul>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div class="geodocs-detail-section">
                            <h3 class="geodocs-section-title">
                                <i class="fas fa-clipboard-list"></i>
                                File Information
                            </h3>
                            <div class="geodocs-info-grid">
                                <div class="geodocs-info-item">
                                    <strong>File Type</strong>
                                    <span>${escapeHtml(doc.fileType)}</span>
                                </div>
                                <div class="geodocs-info-item">
                                    <strong>File Size</strong>
                                    <span>${formatFileSize(doc.fileSize)}</span>
                                </div>
                                <div class="geodocs-info-item">
                                    <strong>Uploaded</strong>
                                    <span>${formatDate(doc.createdAt)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function getLoadingHTML() {
        return `
            <div class="geodocs-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading documents...</p>
            </div>
        `;
    }
    
    function getPaginationHTML() {
        let html = '<div class="geodocs-pagination">';
        
        // Previous button
        html += `<button 
            onclick="window.geodocs.goToPage(${AppState.currentPage - 1})" 
            class="geodocs-btn geodocs-btn-secondary geodocs-btn-sm"
            ${AppState.currentPage === 1 ? 'disabled' : ''}
        >
            <i class="fas fa-chevron-left"></i>
            Previous
        </button>`;
        
        // Page numbers
        html += '<div class="geodocs-pagination-pages">';
        for (let i = 1; i <= AppState.totalPages; i++) {
            if (i === 1 || i === AppState.totalPages || (i >= AppState.currentPage - 2 && i <= AppState.currentPage + 2)) {
                html += `<button 
                    onclick="window.geodocs.goToPage(${i})" 
                    class="geodocs-pagination-btn ${i === AppState.currentPage ? 'active' : ''}"
                >
                    ${i}
                </button>`;
            } else if (i === AppState.currentPage - 3 || i === AppState.currentPage + 3) {
                html += '<span class="geodocs-pagination-ellipsis">...</span>';
            }
        }
        html += '</div>';
        
        // Next button
        html += `<button 
            onclick="window.geodocs.goToPage(${AppState.currentPage + 1})" 
            class="geodocs-btn geodocs-btn-secondary geodocs-btn-sm"
            ${AppState.currentPage === AppState.totalPages ? 'disabled' : ''}
        >
            Next
            <i class="fas fa-chevron-right"></i>
        </button>`;
        
        html += '</div>';
        return html;
    }
    
    // ============================================================================
    // EVENT LISTENERS
    // ============================================================================
    function attachEventListeners() {
        // File input
        const fileInput = document.getElementById('geodocs-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileUpload(e.target.files[0]);
                }
            });
        }
        
        // Drag & drop
        const dropZone = document.getElementById('geodocs-drop-zone');
        if (dropZone) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                if (e.dataTransfer.files.length > 0) {
                    handleFileUpload(e.dataTransfer.files[0]);
                }
            });
        }
        
        // Search input
        const searchInput = document.getElementById('geodocs-search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    AppState.searchQuery = e.target.value;
                    AppState.currentPage = 1;
                    loadDocuments();
                }, 500);
            });
        }
    }
    
    // ============================================================================
    // VIEW MANAGEMENT
    // ============================================================================
    function showDashboard() {
        AppState.currentView = 'dashboard';
        AppState.selectedDocument = null;
        renderApp();
    }
    
    function showUpload() {
        AppState.currentView = 'upload';
        renderApp();
    }
    
    function viewDocument(id) {
        const doc = AppState.documents.find(d => d.id === id);
        if (!doc) return;
        
        AppState.selectedDocument = doc;
        AppState.currentView = 'document-detail';
        renderApp();
    }
    
    function editDocument(id) {
        const doc = AppState.documents.find(d => d.id === id);
        if (!doc) return;
        
        const newTitle = prompt('Edit title:', doc.title);
        if (newTitle && newTitle !== doc.title) {
            updateDocument(id, { 
                title: newTitle, 
                description: doc.description,
                categoryId: doc.categoryId 
            });
        }
    }
    
    function filterByCategory(categoryId) {
        AppState.selectedCategory = categoryId;
        AppState.currentPage = 1;
        loadDocuments();
    }
    
    function setViewMode(mode) {
        AppState.viewMode = mode;
        renderApp();
    }
    
    function goToPage(page) {
        if (page < 1 || page > AppState.totalPages) return;
        AppState.currentPage = page;
        loadDocuments();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // ============================================================================
    // FILE HANDLING
    // ============================================================================
    function handleFileUpload(file) {
        // Validate file type
        const allowedTypes = geodocs.allowedTypes.map(ext => {
            switch(ext.trim()) {
                case 'pdf': return 'application/pdf';
                case 'jpg':
                case 'jpeg': return 'image/jpeg';
                case 'png': return 'image/png';
                case 'gif': return 'image/gif';
                case 'webp': return 'image/webp';
                default: return '';
            }
        }).filter(t => t);
        
        if (!allowedTypes.includes(file.type)) {
            showNotification('Invalid file type. Please upload: ' + geodocs.allowedTypes.join(', '), 'error');
            return;
        }
        
        // Validate file size
        if (file.size > geodocs.maxFileSize) {
            showNotification(`File too large. Maximum size is ${Math.round(geodocs.maxFileSize / 1024 / 1024)} MB.`, 'error');
            return;
        }
        
        uploadDocument(file);
    }
    
    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(timestamp) {
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        
        return date.toLocaleDateString();
    }
    
    function formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    function showNotification(message, type = 'info') {
        const colors = {
            success: 'geodocs-notification-success',
            error: 'geodocs-notification-error',
            info: 'geodocs-notification-info',
            warning: 'geodocs-notification-warning',
        };
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle',
        };
        
        const notification = document.createElement('div');
        notification.className = `geodocs-notification ${colors[type]}`;
        notification.innerHTML = `<i class="fas ${icons[type]}"></i>${escapeHtml(message)}`;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // ============================================================================
    // EXPOSE PUBLIC API
    // ============================================================================
    window.geodocs = window.geodocs || {};
    Object.assign(window.geodocs, {
        showDashboard,
        showUpload,
        viewDocument,
        editDocument,
        deleteDocument,
        filterByCategory,
        setViewMode,
        goToPage,
    });
    
})();
