$(document).ready(function() {
    function updateClock() {
        var now = new Date();
        var thaiMonths = [
            'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];
        
        var dateStr = now.getDate() + ' ' + thaiMonths[now.getMonth()] + ' ' + (now.getFullYear() + 543);
        
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var seconds = String(now.getSeconds()).padStart(2, '0');
        var timeStr = hours + ':' + minutes + ':' + seconds;
        
        var fullText = dateStr + ' ' + timeStr;
        
        var clockLink = $('#navbar-clock');
        if (clockLink.length) {
            var icon = clockLink.find('i');
            if (icon.length) {
                clockLink.html(icon.prop('outerHTML') + ' ' + fullText);
            } else {
                clockLink.text(fullText);
            }
        }
    }
    
    if ($('#navbar-clock').length && !$('#examForm').length) {
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Sidebar logout button click handler
    $(document).on('click', '#sidebar-logout-item', function(e) {
        e.preventDefault();
        
        var logoutForm = $('#logout-form');
        if (logoutForm.length === 0) {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            logoutForm = $('<form>', {
                id: 'logout-form',
                action: '/logout',
                method: 'POST',
                style: 'display: none;'
            }).append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: csrfToken
            }));
            $('body').append(logoutForm);
        }
        logoutForm.submit();
    });

    // Move #sidebar-user-item out of <ul> into .sidebar div so position:absolute works
    var userItem = $('#sidebar-user-item');
    if (userItem.length) {
        // Wrap the li contents into a nice card div, then append to .sidebar
        var sidebarDiv = $('.main-sidebar .sidebar');
        var userCard = $('<div>', { id: 'sidebar-user-card' });
        
        // Get name and icon from the anchor inside
        var anchor = userItem.find('> a');
        var icon = anchor.find('.nav-icon').clone();
        var nameText = anchor.find('p').first().text().trim();
        var logoutLi = userItem.find('#sidebar-logout-item');

        // Build new card HTML
        userCard.html(`
            <div id="sidebar-user-toggle" style="cursor:pointer; display:flex; align-items:center; gap:8px; padding:10px 12px;">
                <i class="fas fa-user-circle" style="font-size:1.3rem; color:#007bff;"></i>
                <span style="color:#343a40; font-weight:500; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${nameText}</span>
                <i class="fas fa-chevron-up" id="sidebar-user-chevron" style="font-size:0.7rem; color:#6c757d; transition:transform 0.2s;"></i>
            </div>
            <div id="sidebar-user-submenu" style="display:none; padding:4px 0;">
                <div id="sidebar-logout-btn" style="cursor:pointer; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:6px; margin:2px 8px; transition:background 0.15s;">
                    <i class="fas fa-sign-out-alt" style="color:#dc3545;"></i>
                    <span style="color:#dc3545; font-weight:600;">ออกจากระบบ</span>
                </div>
            </div>
        `);

        sidebarDiv.append(userCard);

        // Toggle submenu
        userCard.on('click', '#sidebar-user-toggle', function() {
            var submenu = $('#sidebar-user-submenu');
            var chevron = $('#sidebar-user-chevron');
            submenu.slideToggle(150);
            chevron.css('transform', submenu.is(':hidden') ? 'rotate(0deg)' : 'rotate(180deg)');
        });

        // Logout
        userCard.on('click', '#sidebar-logout-btn', function() {
            var logoutForm2 = $('#logout-form');
            if (logoutForm2.length === 0) {
                var csrfToken = $('meta[name="csrf-token"]').attr('content');
                logoutForm2 = $('<form>', { id: 'logout-form', action: '/logout', method: 'POST', style: 'display:none;' })
                    .append($('<input>', { type: 'hidden', name: '_token', value: csrfToken }));
                $('body').append(logoutForm2);
            }
            logoutForm2.submit();
        });

        // Hover effect on logout
        userCard.on('mouseenter', '#sidebar-logout-btn', function() {
            $(this).css('background', 'rgba(220, 53, 69, 0.15)');
        }).on('mouseleave', '#sidebar-logout-btn', function() {
            $(this).css('background', 'transparent');
        });

        // Remove original item from nav
        userItem.closest('li').remove();
    }

    // Keep sidebar treeview submenus open by default
    $('.nav-sidebar .has-treeview').each(function() {
        var treeview = $(this);
        treeview.addClass('menu-open');
        treeview.children('.nav-treeview').show();
    });

    // Navbar Refresh button click handler (acts just like browser reload button)
    $(document).on('click', '#navbar-refresh-btn, #navbar-refresh-btn a, .navbar-refresh-link', function(e) {
        e.preventDefault();
        var icon = $(this).find('i');
        if (icon.length) {
            icon.addClass('fa-spin');
        }
        window.location.reload();
    });

    // Mobile viewport & pinch zoom prevention on student and exam screens
    var isStudentOrExam = window.location.pathname.indexOf('/student') !== -1 || $('#examForm').length > 0;
    if (isStudentOrExam) {
        // Enforce user-scalable=no on viewport meta
        var viewport = document.querySelector('meta[name="viewport"]');
        if (viewport) {
            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover');
        } else {
            var meta = document.createElement('meta');
            meta.name = 'viewport';
            meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover';
            document.getElementsByTagName('head')[0].appendChild(meta);
        }

        // Prevent iOS pinch zoom
        document.addEventListener('touchstart', function(event) {
            if (event.touches && event.touches.length > 1) {
                event.preventDefault();
            }
        }, { passive: false });

        // Prevent iOS gesture zoom
        document.addEventListener('gesturestart', function(e) { e.preventDefault(); });
        document.addEventListener('gesturechange', function(e) { e.preventDefault(); });
        document.addEventListener('gestureend', function(e) { e.preventDefault(); });
    }
});
