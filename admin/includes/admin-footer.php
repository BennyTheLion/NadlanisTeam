  </main>
</div>
<script>
(function(){
  var toggle = document.getElementById('adminMenuToggle');
  var sidebar = document.getElementById('adminSidebar');
  if (!toggle || !sidebar) return;
  toggle.addEventListener('click', function(){
    var open = sidebar.getAttribute('data-open') === 'true';
    sidebar.setAttribute('data-open', open ? 'false' : 'true');
    toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
  });
})();
</script>
</body>
</html>
