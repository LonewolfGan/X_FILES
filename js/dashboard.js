(function() {
    var btn = document.getElementById('dash-theme-toggle');
    if (!btn) return;
    var html = document.documentElement;

    function updateIcon() {
        btn.innerHTML = html.getAttribute('data-theme') === 'dark'
            ? '<i class="fa-solid fa-sun"></i>'
            : '<i class="fa-solid fa-moon"></i>';
    }
    updateIcon();
    btn.addEventListener('click', function() {
        var t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', t);
        localStorage.setItem('theme', t);
        updateIcon();
    });
})();

(function() {
    var sidebar = document.querySelector('.dashboard-sidebar');
    if (!sidebar) return;

    var overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    var toggleBtn = document.createElement('button');
    toggleBtn.className = 'mobile-sidebar-toggle';
    toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
    toggleBtn.setAttribute('aria-label', 'Menu');
    document.body.appendChild(toggleBtn);

    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        toggleBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });
})();
