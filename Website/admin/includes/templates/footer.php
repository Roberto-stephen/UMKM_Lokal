</div><!-- end .page-content -->

    <footer class="admin-footer">
      &copy; <?php echo date('Y') ?> MakanLokal Admin Panel &mdash; Universitas Buddhi Dharma
    </footer>

  </div><!-- end .main-content -->

</div><!-- end .admin-wrapper -->

<script src="<?php echo $js ?>jquery-1.12.1.min.js"></script>
<script src="<?php echo $js ?>jquery-ui.min.js"></script>
<script src="<?php echo $js ?>bootstrap.min.js"></script>
<script>
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    var mc = document.getElementById('main-content');
    var collapsed = sb.getAttribute('data-collapsed') === '1';
    if (collapsed) {
        sb.style.width = '240px';
        sb.style.minWidth = '240px';
        sb.setAttribute('data-collapsed', '0');
        localStorage.setItem('sbCollapsed', '0');
    } else {
        sb.style.width = '60px';
        sb.style.minWidth = '60px';
        sb.setAttribute('data-collapsed', '1');
        localStorage.setItem('sbCollapsed', '1');
    }
}
(function(){
    var sb = document.getElementById('sidebar');
    if (!sb) return;
    if (localStorage.getItem('sbCollapsed') === '1') {
        sb.style.width = '60px';
        sb.style.minWidth = '60px';
        sb.setAttribute('data-collapsed', '1');
    }
})();
</script>
</body>
</html>