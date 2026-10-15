    </main>
    
    <!-- FOOTER MODERN -->
    <footer class="footer-custom">
        <div class="container-fluid px-lg-4">
            <div class="row align-items-center gy-2">
                <div class="col-md-6 text-center text-md-start">
                    <span class="fw-semibold text-dark">SPK Pengadaan Bahan Baku Gindaco</span> &copy; <?php echo date('Y'); ?> 
                    <span class="mx-2 text-muted">&bull;</span>
                    <span class="text-muted">Mall Artha Gading Branch</span>
                </div>
                <div class="col-md-6 text-center text-md-end text-muted small">
                    <span class="badge bg-light text-secondary border me-2"><i class="fas fa-code-branch me-1"></i>Metode Exponential Smoothing & BOM</span>
                    <span>Tugas Akhir Teknik Informatika</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('.select2').length > 0) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
        });
    </script>
</body>
</html>

