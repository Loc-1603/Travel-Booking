
<!-- JAVASCRIPT -->
<script src="{{asset('admin/dist/assets/libs/jquery/jquery.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/metismenu/metisMenu.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/simplebar/simplebar.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/node-waves/waves.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/feather-icons/feather.min.js')}}"></script>
<!-- pace js -->
<script src="{{asset('admin/dist/assets/libs/pace-js/pace.min.js')}}"></script>

<!-- apexcharts -->
<script src="{{asset('admin/dist/assets/libs/apexcharts/apexcharts.min.js')}}"></script>

<!-- Plugins js-->
<script src="{{asset('admin/dist/assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/admin-resources/jquery.vectormap/maps/jquery-jvectormap-world-mill-en.js')}}"></script>
<!-- dashboard init -->
<script src="{{asset('admin/dist/assets/js/pages/dashboard.init.js')}}"></script>

<script src="{{asset('admin/dist/assets/js/pages/pass-addon.init.js')}}"></script>

<script src="{{asset('admin/dist/assets/js/app.js')}}"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  function saveTheme(mode){
    try{
      localStorage.setItem('admin_theme_mode', mode);
      document.cookie = 'admin_theme=' + encodeURIComponent(mode) + '; path=/; max-age=' + (60*60*24*365) + '; SameSite=Lax';
    }catch(e){}
  }
  function getCurrentMode(){
    return document.body.getAttribute('data-bs-theme') || 'light';
  }
  var btn = document.getElementById('mode-setting-btn');
  if(btn){
    btn.addEventListener('click', function(){
      setTimeout(function(){ saveTheme(getCurrentMode()); }, 0);
    });
  }
  document.addEventListener('change', function(e){
    if(e.target && e.target.name === 'layout-mode'){
      var mode = e.target.value;
      if(mode === 'dark' || mode === 'light'){
        setTimeout(function(){ saveTheme(mode); }, 0);
      }
    }
  });
});
</script>
</body>
</html>

