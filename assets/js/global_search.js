// Global Search Functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    let searchTimeout;
    let currentSearchQuery = '';

    if (!searchInput || !suggestionsContainer) return;

    // Search input event listener
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            hideSuggestions();
            return;
        }

        // Debounce search
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.global-search-container')) {
            hideSuggestions();
        }
    });

    // Handle suggestion clicks
    suggestionsContainer.addEventListener('click', function(e) {
        const suggestionItem = e.target.closest('.suggestion-item');
        if (suggestionItem) {
            const url = suggestionItem.dataset.url;
            if (url) {
                window.location.href = url;
            }
        }
    });

    // Handle Enter key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.trim();
            if (query.length >= 2) {
                window.location.href = `search_results.php?q=${encodeURIComponent(query)}`;
            }
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });

    // Handle arrow keys for navigation
    searchInput.addEventListener('keydown', function(e) {
        const suggestions = suggestionsContainer.querySelectorAll('.suggestion-item');
        const activeSuggestion = suggestionsContainer.querySelector('.suggestion-item.active');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const next = activeSuggestion.nextElementSibling;
                if (next) {
                    next.classList.add('active');
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else {
                suggestions[0]?.classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const prev = activeSuggestion.previousElementSibling;
                if (prev) {
                    prev.classList.add('active');
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else {
                suggestions[suggestions.length - 1]?.classList.add('active');
            }
        }
    });

    function performSearch(query) {
        if (query === currentSearchQuery) return;
        
        currentSearchQuery = query;
        showLoading();

        fetch(`global_search_api.php?q=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                if (data.results && data.results.length > 0) {
                    displaySuggestions(data.results);
                } else {
                    showNoResults();
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                showError();
            });
    }

    function displaySuggestions(results) {
        suggestionsContainer.innerHTML = '';
        
        results.forEach(result => {
            const suggestionItem = document.createElement('div');
            suggestionItem.className = 'suggestion-item';
            suggestionItem.dataset.url = result.url;
            
            const iconClass = getIconClass(result.type);
            
            suggestionItem.innerHTML = `
                <div class="suggestion-icon">
                    <i class="${iconClass}"></i>
                </div>
                <div class="suggestion-content">
                    <div class="suggestion-title">${escapeHtml(result.title)}</div>
                    <div class="suggestion-description">${escapeHtml(result.description)}</div>
                </div>
                <div class="suggestion-type">${result.type}</div>
            `;
            
            suggestionsContainer.appendChild(suggestionItem);
        });
        
        showSuggestions();
    }

    function showLoading() {
        suggestionsContainer.innerHTML = '<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        showSuggestions();
    }

    function showNoResults() {
        suggestionsContainer.innerHTML = '<div class="search-no-results">No results found</div>';
        showSuggestions();
    }

    function showError() {
        suggestionsContainer.innerHTML = '<div class="search-no-results">Search error occurred</div>';
        showSuggestions();
    }

    function showSuggestions() {
        suggestionsContainer.style.display = 'block';
    }

    function hideSuggestions() {
        suggestionsContainer.style.display = 'none';
        suggestionsContainer.innerHTML = '';
        currentSearchQuery = '';
    }

    function getIconClass(type) {
        switch (type) {
            case 'campaign':
                return 'fas fa-bullhorn';
            case 'ad':
                return 'fas fa-ad';
            case 'adset':
                return 'fas fa-layer-group';
            case 'content':
                return 'fas fa-file-alt';
            default:
                return 'fas fa-search';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Add CSS for active suggestion
const style = document.createElement('style');
style.textContent = `
    .suggestion-item.active {
        background: #e3f2fd !important;
    }
    
    .suggestion-item.active .suggestion-title {
        color: #1976d2;
    }
`;
document.head.appendChild(style);

