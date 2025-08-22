            </div>
            <!-- End of container-fluid -->
            
            <!-- Footer -->
            <footer class="sticky-footer bg-white py-3">
                <div class="container">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="text-muted">
                            &copy; <?php echo date('Y'); ?> MindSparxs. All rights reserved.
                        </div>
                        <div class="d-flex
                            <a href="#" class="text-muted me-3">Privacy Policy</a>
                            <a href="#" class="text-muted">Terms of Service</a>
                        </div>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
            
        </div>
        <!-- End of Main Content -->
    </div>
    
    <!-- Scroll to Top Button-->
    <button class="btn btn-primary btn-scroll-top" id="scrollTopBtn" title="Go to top">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <!-- Core JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simplebar/6.0.0/simplebar.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom scripts -->
    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    document.body.classList.toggle('sidebar-show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInside = sidebar.contains(event.target) || 
                                    (sidebarToggle && sidebarToggle.contains(event.target));
                
                if (!isClickInside && window.innerWidth < 768) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-show');
                }
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Scroll to top button
            const scrollTopBtn = document.getElementById('scrollTopBtn');
            if (scrollTopBtn) {
                window.onscroll = function() {
                    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                        scrollTopBtn.style.display = 'block';
                    } else {
                        scrollTopBtn.style.display = 'none';
                    }
                };
                
                scrollTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
            
            // Initialize SimpleBar for custom scrollbars
            new SimpleBar(document.querySelector('.sidebar'));
            
            // Active menu item highlighting
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                const href = link.getAttribute('href');
                if (href && (href === currentPage || 
                    (href.endsWith('.php') && currentPage.startsWith(href.replace('.php', ''))))) {
                    link.classList.add('active');
                }
            });
        });
        document.querySelector('.sidebar').addEventListener('mousewheel', function(e) {
            if (this.scrollTop === 0 && e.deltaY < 0) {
                e.preventDefault();
                this.scrollTop = 1;
            } else if (this.scrollTop + this.clientHeight === this.scrollHeight && e.deltaY > 0) {
                e.preventDefault();
                this.scrollTop -= 1;
            }
        });
    </script>
</body>
</html>
