
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

<!-- flatpickr (date picker with Vietnamese locale) -->
<script src="{{asset('admin/dist/assets/libs/flatpickr/flatpickr.min.js')}}"></script>
<script src="{{asset('admin/dist/assets/libs/flatpickr/l10n/vn.js')}}"></script>

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

<script>
document.addEventListener('DOMContentLoaded', function(){
  if (typeof flatpickr !== 'undefined') {
    // Initialize flatpickr on all date inputs for Vietnamese locale
    try {
      // flatpickr uses 'vn' code for Vietnamese
      if (flatpickr.l10ns && flatpickr.l10ns.vn) {
        flatpickr.localize(flatpickr.l10ns.vn);
      }
    } catch(e) {}
    document.querySelectorAll('input[type="date"]').forEach(function(el){
      // Avoid double init
      if (el.dataset.flatpickr) return;
      el.dataset.flatpickr = '1';
      flatpickr(el, {
        dateFormat: 'Y-m-d',
        locale: 'vn',
        allowInput: true
      });
    });
  }
});
</script>

@auth
@if(auth()->user()->role === \App\Enums\Role::VENDOR)
@php
    $liveBookingUuid = request()->routeIs('admin.vendor.tour-messages.show') ? request()->route('uuid') : null;
    $liveConfig = [
        'reverb' => [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ],
        'vendorId' => auth()->id(),
        'authEndpoint' => '/broadcasting/auth',
        'bookingUuid' => $liveBookingUuid,
        'indexUrl' => route('admin.vendor.tour-messages.show', ['uuid' => '__UUID__']),
        'readUrl' => $liveBookingUuid ? route('admin.vendor.tour-messages.read', ['uuid' => $liveBookingUuid]) : null,
        'replyUrl' => $liveBookingUuid ? route('admin.vendor.tour-messages.reply', ['uuid' => $liveBookingUuid]) : null,
        'readUrlTemplate' => route('admin.vendor.tour-messages.read', ['uuid' => '__UUID__']),
        'replyUrlTemplate' => route('admin.vendor.tour-messages.reply', ['uuid' => '__UUID__']),
        'labels' => [
            'read' => __('admin.vendor.tour_messages.detail.read'),
            'unread' => __('admin.vendor.tour_messages.detail.unread'),
            'open' => __('admin.vendor.tour_messages.open'),
        ],
    ];
@endphp
<script>window.TourMessagesLive = {{ \Illuminate\Support\Js::from($liveConfig) }};</script>
@vite(['resources/js/admin-live.js'])
@endif
@endauth
</body>
</html>

