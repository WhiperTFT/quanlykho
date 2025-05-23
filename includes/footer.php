<?php // quanlykho/includes/footer.php ?>
    </main>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">&copy; <?= date('Y') ?> <?= htmlspecialchars($lang['appName'] ?? 'Quản Lý Kho', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</span>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/helpers.js?v=<?= time() ?>"></script>
    <?php if (isset($current_page_js) && !empty($current_page_js)): ?>
        <script src="assets/js/<?= htmlspecialchars($current_page_js); ?>?v=<?= time(); ?>"></script>
    <?php endif; ?>

    

</body>
</html>