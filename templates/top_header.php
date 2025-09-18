<!-- Top Header -->
<header class="top-header">
    <div class="header-left">
        <button class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
    </div>
    
    <div class="header-center">
        <div class="global-search-container">
            <form method="GET" action="search_results.php" class="global-search-form">
                <div class="search-input-wrapper">
                    <input type="text" 
                           name="q" 
                           placeholder="Search campaigns, ads, content..." 
                           class="global-search-input"
                           id="globalSearchInput">
                    <button type="submit" class="global-search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <div class="search-suggestions" id="searchSuggestions"></div>
        </div>
    </div>
    
    <div class="header-right">
        <?php if (isset($header_actions)): ?>
            <?php echo $header_actions; ?>
        <?php endif; ?>
    </div>
</header>
