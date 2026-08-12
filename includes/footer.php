    </main><!-- /main-content -->

    <footer class="footer" role="contentinfo">
        <div class="footer-container">
            <div class="footer-content">

                <div class="footer-section">
                    <h3>Graduate Job Connect</h3>
                    <p class="footer-tagline">GJC | Connecting Graduates with Opportunities</p>
                    <p style="color:rgba(255,255,255,.75);line-height:1.7;">
                        Bridging talented Ethiopian graduates with employers looking for fresh, motivated talent.
                    </p>
                </div>

                <?php
                // $_prefix is set by header.php. If footer is somehow included standalone, compute it here.
                if (!isset($_prefix)) {
                    $__root   = str_replace('\\', '/', dirname(__DIR__) . '/includes/../');
                    $__root   = str_replace('\\', '/', realpath(dirname(__DIR__)));
                    $__script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
                    if ($__script === $__root) {
                        $_prefix = '';
                    } else {
                        $__depth = substr_count(str_replace($__root, '', $__script), '/');
                        $_prefix = str_repeat('../', max(0, $__depth));
                    }
                }
                // $_jsBase is set by header.php too
                if (!isset($_jsBase)) {
                    $_jsBase = $_prefix . 'assets/js/';
                }
                ?>

                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo $_prefix; ?>index.php">Home</a></li>
                        <?php if (!isLoggedIn()): ?>
                            <li><a href="<?php echo $_prefix; ?>login.php">Login</a></li>
                            <li><a href="<?php echo $_prefix; ?>register.php">Register</a></li>
                        <?php elseif (isGraduate()): ?>
                            <li><a href="<?php echo $_prefix; ?>graduate/jobs.php">Browse Jobs</a></li>
                            <li><a href="<?php echo $_prefix; ?>graduate/my-applications.php">My Applications</a></li>
                            <li><a href="<?php echo $_prefix; ?>graduate/saved-jobs.php">Saved Jobs</a></li>
                        <?php elseif (isEmployer()): ?>
                            <li><a href="<?php echo $_prefix; ?>employer/post-job.php">Post a Job</a></li>
                            <li><a href="<?php echo $_prefix; ?>employer/my-jobs.php">My Jobs</a></li>
                            <li><a href="<?php echo $_prefix; ?>employer/applicants.php">Applicants</a></li>
                        <?php elseif (isAdmin()): ?>
                            <li><a href="<?php echo $_prefix; ?>admin/users.php">Manage Users</a></li>
                            <li><a href="<?php echo $_prefix; ?>admin/jobs.php">Manage Jobs</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Contact</h4>
                    <p style="color:rgba(255,255,255,.75);">
                        📧 <a href="mailto:tedrosweldegebriel465@gmail.com"
                               style="color:rgba(255,255,255,.85);">tedrosweldegebriel465@gmail.com</a>
                    </p>
                    <p style="color:rgba(255,255,255,.75);">📞 +(251) 949 802 587</p>
                    <p style="color:rgba(255,255,255,.75);font-size:.85rem;margin-top:.5rem;">
                        Built for Ethiopian &amp; international universities
                    </p>
                </div>

            </div>

            <div class="footer-bottom">
                <p>
                    &copy; <?php echo date('Y'); ?> Graduate Job Connect &mdash; GJC | Connecting Graduates with Opportunities
                    &mdash; v<?php echo defined('APP_VERSION') ? APP_VERSION : '2.0.0'; ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- JS -->
    <?php
    // $_jsBase is set by header.php; fall back to prefix if header wasn't included
    $jsBase = isset($_jsBase) ? $_jsBase
            : (isset($js_path) ? $js_path : (isset($_prefix) ? $_prefix : '') . 'assets/js/');
    ?>
    <script src="<?php echo $jsBase; ?>script.js"></script>

    <script>
    // Mobile nav toggle
    (function () {
        const toggle = document.getElementById('navToggle');
        const menu   = document.getElementById('navMenu');
        if (!toggle || !menu) return;
        toggle.addEventListener('click', function () {
            const open = menu.classList.toggle('active');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        });
        document.addEventListener('click', function (e) {
            if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') menu.classList.remove('active');
        });
    })();

    // Auto-dismiss alerts after 6 s
    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (el) {
            el.style.transition = 'opacity .4s ease';
            el.style.opacity    = '0';
            setTimeout(function () { el && el.parentNode && el.parentNode.removeChild(el); }, 400);
        });
    }, 6000);

    // Alert close buttons
    document.querySelectorAll('.alert-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = this.closest('.alert');
            if (el) el.parentNode.removeChild(el);
        });
    });
    </script>

</body>
</html>
