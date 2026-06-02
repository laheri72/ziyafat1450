            </main>
            
            <?php if (is_logged_in()): ?>
            <!-- Footer -->
            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Ziyafat-us-Shukr 1450H | Built by 
                <a href="https://github.com/laheri72/" target="_blank" class="text-light ms-1" style="color: #f8f9fa !important; text-decoration: none;">
                    <i class="fab fa-github"></i> <strong>Laheri72</strong>
                </a>
                </p>
            </footer>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo isset($js_path) ? $js_path : '../assets/js/'; ?>script.js"></script>
</body>
</html>