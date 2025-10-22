<?php
// File: includes/footer.php
?>
</main> 
<footer class="footer mt-auto py-3 bg-light"> 
    <div class="container text-center">
        <span class="text-muted">© <?= date('Y') ?> <?= htmlspecialchars($lang['appName'] ?? 'Inventory App', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</span>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="<?= PROJECT_BASE_URL ?>assets/js/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom navbar script -->
<script src="<?= PROJECT_BASE_URL ?>assets/js/navbar.js?v=<?= filemtime(__DIR__ . '/../assets/js/navbar.js') ?>"></script>
<script src="assets/js/number_helpers.js"></script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>window.PROJECT_BASE_URL = "<?= rtrim(PROJECT_BASE_URL, '/').'/'; ?>";</script>
<script src="<?= PROJECT_BASE_URL ?>assets/js/auth_guard.js"></script>
