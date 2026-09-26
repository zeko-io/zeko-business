/* global ZBP */
(function() {
    'use strict';

    ZBP.ready(function() {
        initDirectorySearch();
    });

    function initDirectorySearch() {
        var container = document.getElementById('zbp-directory-container');
        if (!container) return;

        var grid = container.querySelector('.zbp-directory__grid');
        var searchInput = container.querySelector('.zbp-directory__search-input');
        var nearInput = container.querySelector('[data-param="near"]');
        var filterEls = container.querySelectorAll('.zbp-directory__filter');
        var controls = container.querySelectorAll('[data-param]');
        var toggle = container.querySelector('.zbp-directory__view-toggle');

        var debounceTimer;
        var currentPage = 1;

        function schedule(evt) {
            evt.preventDefault();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                currentPage = 1;
                loadBusinesses();
            }, 400);
        }

        if (searchInput) {
            searchInput.addEventListener('input', schedule);
        }

        if (nearInput) {
            nearInput.addEventListener('input', schedule);
            nearInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(debounceTimer); currentPage = 1; loadBusinesses(); } });
        }

        ZBP.each(filterEls, function(el) {
            el.addEventListener('click', function() {
                var active = el.classList.contains('zbp-directory__filter--active');

                ZBP.each(filterEls, function(f) {
                    f.classList.remove('zbp-directory__filter--active');
                    f.setAttribute('aria-pressed', 'false');
                });
                if (!active) {
                    el.classList.add('zbp-directory__filter--active');
                    el.setAttribute('aria-pressed', 'true');
                }
                currentPage = 1;
                loadBusinesses();
            });
        });

        ZBP.each(controls, function(el) {
            el.addEventListener('change', function() {
                currentPage = 1;
                loadBusinesses();
            });
        });

        if (toggle) {
            toggle.addEventListener('click', function() {
                var pressed = toggle.getAttribute('aria-pressed') === 'true';
                toggle.setAttribute('aria-pressed', String(!pressed));
                container.classList.toggle('zbp-directory--list', !pressed);
            });
        }

        if (grid) {
            grid.addEventListener('click', function(e) {
                var link = ZBP.closest(e.target, '.zbp-directory__page-link');
                if (!link) return;
                e.preventDefault();
                var page = parseInt(ZBP.getData(link, 'page'), 10);
                if (page > 0) {
                    currentPage = page;
                    loadBusinesses();
                    var top = container.getBoundingClientRect().top + window.pageYOffset - 80;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        }

        function loadBusinesses() {
            var data = {
                action: 'zbp_directory_search',
                search: searchInput ? searchInput.value : '',
                status: '',
                page: currentPage
            };

            ZBP.each(filterEls, function(el) {
                if (el.classList.contains('zbp-directory__filter--active')) {
                    data.status = ZBP.getData(el, 'status') || '';
                }
            });

            ZBP.each(controls, function(el) {
                var param = ZBP.getData(el, 'param');
                if (!param) return;

                if (el.type === 'checkbox') {
                    if (el.checked) { data[param] = '1'; }
                } else if (el.value) {
                    data[param] = el.value;
                }
            });

            if (grid) { ZBP.showLoading(grid); }

            ZBP.ajax('zbp_directory_search', data).then(function(res) {
                if (grid) { ZBP.hideLoading(grid); }
                if (res && res.success && res.data) {
                    grid.innerHTML = res.data.html || '';
                    grid.setAttribute('data-found', res.data.found || 0);
                }
            }).catch(function() {
                if (grid) { ZBP.hideLoading(grid); }
                grid.innerHTML = '<div class="zbp-directory__empty">' + ZBP.escapeHtml(ZBP.t('error')) + '</div>';
            });
        }
    }

})();
