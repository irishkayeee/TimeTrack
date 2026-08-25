        </main>
        <footer class="sp-copyright">&copy; <?php echo date('Y'); ?> TimeTrack. All rights reserved.</footer>
    </div>
</div>

<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 text-center">
            <div class="modal-body p-4">
                <div class="sp-logout-icon mb-3"><i class="fa-solid fa-right-from-bracket"></i></div>
                <h6 class="mb-1">Log out?</h6>
                <p class="text-muted small mb-4">You'll need to sign in again to access your account.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger rounded-pill px-4">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 text-center">
            <div class="modal-body p-4">
                <div class="sp-confirm-icon mb-3"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <h6 class="mb-1">Are you sure?</h6>
                <p class="text-muted small mb-4" id="confirmActionMessage">Please confirm this action.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="flashResultModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 text-center">
            <div class="modal-body p-4">
                <?php if ($flash): ?>
                    <div class="sp-confirm-icon mb-3 sp-flash-icon-<?php echo htmlspecialchars($flash['type']); ?>">
                        <i class="fa-solid <?php echo $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info'); ?>"></i>
                    </div>
                    <h6 class="mb-1"><?php echo $flash['type'] === 'success' ? 'Success' : ($flash['type'] === 'danger' ? 'Something went wrong' : 'Heads up'); ?></h6>
                    <p class="text-muted small mb-4"><?php echo htmlspecialchars($flash['message']); ?></p>
                <?php endif; ?>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    var spToggle = document.getElementById('spSidebarToggle');
    var spShell = document.getElementById('spShell');
    if (spToggle && spShell) {
        spToggle.addEventListener('click', function () {
            spShell.classList.toggle('sp-sidebar-collapsed');
        });
    }

    var confirmModalEl = document.getElementById('confirmActionModal');
    if (confirmModalEl) {
        var confirmModal = new bootstrap.Modal(confirmModalEl);
        var confirmMessageEl = document.getElementById('confirmActionMessage');
        var confirmActionBtn = document.getElementById('confirmActionBtn');
        var pendingConfirmForm = null;

        document.querySelectorAll('.js-confirm-submit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingConfirmForm = btn.closest('form');
                confirmMessageEl.textContent = btn.getAttribute('data-message') || 'Please confirm this action.';
                confirmModal.show();
            });
        });

        confirmActionBtn.addEventListener('click', function () {
            confirmModal.hide();
            if (pendingConfirmForm) {
                pendingConfirmForm.submit();
            }
        });
    }

    <?php if ($flash): ?>
    var flashModalEl = document.getElementById('flashResultModal');
    if (flashModalEl) {
        new bootstrap.Modal(flashModalEl).show();
    }
    <?php endif; ?>
</script>
</body>
</html>
