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

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net/2.3.8/dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.3.8/dataTables.bootstrap5.min.js"></script>
<script>
    var spToggle = document.getElementById('spSidebarToggle');
    var spShell = document.getElementById('spShell');
    if (spToggle && spShell) {
        spToggle.addEventListener('click', function () {
            spShell.classList.toggle('sp-sidebar-collapsed');
        });
    }
</script>
</body>
</html>
