<aside id="sidebar-left" class="sidebar-left">
  <div class="sidebar-header">
    <div class="sidebar-title pt-2" style="display: flex;justify-content: space-between;">
      <a href="/" class="logo col-11">						
        <img src="/assets/img/lb-logo.jpg" class="sidebar-logo" alt="Brand Logo" height="12%" />
      </a>
      <div class="d-md-none toggle-sidebar-left col-1" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
        <i class="fas fa-times" aria-label="Toggle sidebar"></i>
      </div>
    </div>
    <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
      <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
    </div>
  </div>

  <div class="nano">
    <div class="nano-content">
      <nav id="menu" class="nav-main" role="navigation">
        <ul class="nav nav-main">
          <li class="active">
            <a class="nav-link" href="/"> <i class="fa fa-home" aria-hidden="true"></i><span>Dashboard</span></a>    
          </li>

          <li class="nav-parent">
            <a class="nav-link" href="#">
              <i style="font-size:16px" class="fa fa-layer-group" aria-hidden="true"></i>
              <span>Projects</span>
            </a>
            <ul class="nav nav-children">
              <li><a class="nav-link">Status</a></li>
              <li><a class="nav-link">All Projects</a></li>	
            </ul>
          </li>
        </ul>	
      </nav>
		</div>

    <script>
      // Maintain Scroll Position
      if (typeof localStorage !== 'undefined') {
        if (localStorage.getItem('sidebar-left-position') !== null) {
          var initialPosition = localStorage.getItem('sidebar-left-position'),
          sidebarLeft = document.querySelector('#sidebar-left .nano-content');

          sidebarLeft.scrollTop = initialPosition;
        }
      }
    </script>
  </div>
</aside>