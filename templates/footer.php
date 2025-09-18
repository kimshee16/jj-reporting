    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inline_scripts)): ?>
        <script>
            <?php echo $inline_scripts; ?>
        </script>
    <?php endif; ?>
    
    <!-- Common JavaScript for sidebar functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle for mobile
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (sidebar && sidebarToggle && 
                        !sidebar.contains(e.target) && 
                        !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        });
    </script>
</body>
</html>
