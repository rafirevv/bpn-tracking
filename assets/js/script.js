document.addEventListener('DOMContentLoaded', function () {

    /* ---- Sidebar toggle (mobile) ---- */
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggleBtn = document.getElementById('sidebarToggle');
    var closeBtn = document.getElementById('sidebarCloseBtn');

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar) sidebar.classList.toggle('is-open');
            if (overlay) overlay.classList.toggle('is-open');
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });

    // Auto close sidebar on mobile when navigating
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav .nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });
    }

    /* ---- Confirm dialog untuk aksi penting (hapus user, dsb) ---- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    /* ---- Kartu pilihan aksi (Teruskan / Kembalikan) di halaman proses.php ---- */
    var actionRadios = document.querySelectorAll('.action-choice-radio');
    var catatanWrap = document.getElementById('catatanWrapper');
    var catatanInput = document.getElementById('catatanInput');
    var catatanHint = document.getElementById('catatanHint');
    var submitBtn = document.getElementById('btnSubmitProses');

    function refreshActionUI() {
        actionRadios.forEach(function (radio) {
            var card = document.getElementById('card-' + radio.value);
            if (card) card.classList.toggle('is-checked', radio.checked);
        });

        var checked = document.querySelector('.action-choice-radio:checked');
        if (!checked || !catatanWrap) return;

        var requiresNote = checked.getAttribute('data-requires-note') === '1';
        catatanWrap.style.display = 'block';
        catatanInput.required = requiresNote;
        catatanHint.textContent = requiresNote
            ? 'Wajib diisi — jelaskan kekurangan/alasan sehingga penerima mengetahui tindak lanjut yang diperlukan.'
            : 'Opsional — tambahkan catatan penyelesaian jika diperlukan.';

        if (submitBtn) {
            var label = checked.getAttribute('data-btn-label') || 'Simpan';
            submitBtn.innerHTML = label;
            submitBtn.className = 'btn w-100 ' + (checked.getAttribute('data-btn-class') || 'btn-primary');
        }
    }

    actionRadios.forEach(function (radio) {
        radio.addEventListener('change', refreshActionUI);
    });
    if (actionRadios.length) refreshActionUI();

    /* ---- Auto-dismiss alert setelah beberapa detik ---- */
    document.querySelectorAll('.alert.show').forEach(function (alertEl) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            bsAlert.close();
        }, 6000);
    });

    /* ==========================================================
       IN-APP NOTIFICATION INTERACTION & POLLING
       ========================================================== */
    var btnMarkAllRead = document.getElementById('btnMarkAllRead');
    var badgeCounter = document.getElementById('notifBadgeCounter');
    var badgeHeader = document.getElementById('notifBadgeHeader');
    var notifList = document.getElementById('notifListContainer');

    function updateBadgeDisplay(count) {
        if (!badgeCounter) return;
        if (count > 0) {
            badgeCounter.textContent = count > 99 ? '99+' : count;
            badgeCounter.classList.remove('d-none');
            if (badgeHeader) {
                badgeHeader.textContent = count + ' Baru';
                badgeHeader.classList.remove('d-none');
            }
            if (btnMarkAllRead) {
                btnMarkAllRead.classList.remove('d-none');
            }
        } else {
            badgeCounter.classList.add('d-none');
            if (badgeHeader) badgeHeader.classList.add('d-none');
            if (btnMarkAllRead) btnMarkAllRead.classList.add('d-none');
        }
    }

    // Ambil base URL secara aman
    var getBaseUrl = function () {
        if (window.BASE_URL) return window.BASE_URL;
        var link = document.querySelector('link[href*="assets/css/style.css"]');
        if (!link) return '';
        var href = link.getAttribute('href').split('?')[0];
        return href.replace('assets/css/style.css', '');
    };

    if (btnMarkAllRead) {
        btnMarkAllRead.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            fetch(getBaseUrl() + 'api/notifikasi.php?action=read_all', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    updateBadgeDisplay(0);
                    if (notifList) {
                        notifList.querySelectorAll('.notif-item').forEach(function (item) {
                            item.classList.remove('is-unread');
                        });
                        notifList.querySelectorAll('.notif-unread-dot').forEach(function (dot) {
                            dot.remove();
                        });
                    }
                }
            })
            .catch(function (err) {
                console.error('Gagal menandai notifikasi dibaca:', err);
            });
        });
    }

    // Polling periodik (setiap 30 detik) untuk memeriksa berkas masuk baru
    if (badgeCounter) {
        function pollNotifications() {
            fetch(getBaseUrl() + 'api/notifikasi.php?action=check', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    updateBadgeDisplay(data.unread_count);

                    if (notifList && data.items && data.items.length > 0) {
                        var html = '';
                        data.items.forEach(function (n) {
                            var unreadClass = !n.is_read ? 'is-unread' : '';
                            var unreadDot = !n.is_read ? '<span class="notif-unread-dot" style="width:8px !important; min-width:8px !important; height:8px !important; border-radius:50% !important; background-color:#0D6EFD !important; flex-shrink:0 !important; margin-top:6px !important;"></span>' : '';
                            var badgeNo = n.nomor ? '<div class="mt-1"><span class="badge text-bg-light border text-muted mono" style="font-size:11px !important; padding:2px 7px !important;">' + n.nomor + '</span></div>' : '';
                            var styleAttr = n.style_attr || 'style="background-color:#EFF6FF;color:#2563EB;"';

                            html += '<a href="' + n.link + '" class="notif-item ' + unreadClass + '" data-id="' + n.id + '" style="display:flex !important; flex-direction:row !important; align-items:flex-start !important; gap:12px !important; padding:12px 16px !important; border-bottom:1px solid #F1F5F9 !important; text-decoration:none !important; color:inherit !important;">'
                                  + '<div class="notif-icon-circle" ' + styleAttr + ' style="width:38px !important; min-width:38px !important; height:38px !important; border-radius:50% !important; display:inline-flex !important; align-items:center !important; justify-content:center !important; flex-shrink:0 !important; font-size:1.05rem !important;">'
                                  + '<i class="bi ' + n.icon + '"></i>'
                                  + '</div>'
                                  + '<div class="notif-content" style="flex:1 1 auto !important; min-width:0 !important;">'
                                  + '<div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">'
                                  + '<span class="notif-title ' + (!n.is_read ? 'fw-bold text-dark' : 'fw-semibold text-secondary') + '" style="font-size:0.86rem !important; line-height:1.35 !important;">' + n.judul + '</span>'
                                  + '<span class="notif-time text-muted small flex-shrink-0" style="font-size:0.72rem !important; white-space:nowrap !important;">' + n.waktu_rel + '</span>'
                                  + '</div>'
                                  + '<div class="notif-desc small text-muted mb-1" style="font-size:0.78rem !important; line-height:1.4 !important;">' + n.pesan + '</div>'
                                  + badgeNo
                                  + '</div>'
                                  + unreadDot
                                  + '</a>';
                        });
                        notifList.innerHTML = html;
                    }
                }
            })
            .catch(function () {
                // Jangan tampilkan error console saat koneksi terputus sesaat
            });
        }

        setInterval(pollNotifications, 30000);
    }
});

