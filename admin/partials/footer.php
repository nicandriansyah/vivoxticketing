        </main>
    </div><!-- /adm-content -->
</div><!-- /adm-layout -->
<script>
// Pesan flash auto-close dalam 30 detik
setTimeout(function () {
    document.querySelectorAll('.adm-flash').forEach(function (el) {
        el.style.transition = 'opacity 0.5s ease';
        el.style.opacity = '0';
        setTimeout(function () { el.remove(); }, 500);
    });
}, 30000);
</script>
</body>
</html>
