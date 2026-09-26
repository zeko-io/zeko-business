/* global ZBP, wp, Quill, tinyMCE */
(function() {
    'use strict';

    var quillInstances = [];
    var initializedQuills = (typeof WeakSet === 'function') ? new WeakSet() : null;

    ZBP.ready(function() {
        initPortalTabs();
        initPortalForms();
        initServiceActions();
        initServiceCategoryAdd();
        initMediaPicker();
        initMediaDelete();
        initBusinessSwitch();
        initHoursCopy();
        initHoursMode();
        initInviteTabs();
        initInviteActions();
        initServiceImageUpload();
        initProductActions();
        initProductMediaUpload();
    });

    function setHidden(el, hidden) {
        if (!el) { return; }
        if (hidden) { el.setAttribute('hidden', ''); } else { el.removeAttribute('hidden'); }
    }

    function fireQaContentLoaded() {
        if (window.jQuery) {
            window.jQuery(document).trigger('zeko_qa_content_loaded');
        } else {
            document.dispatchEvent(new CustomEvent('zeko_qa_content_loaded'));
        }
    }

    function destroyQuills() {
        quillInstances.forEach(function(q) {
            try { q.quill = null; } catch (e) {}
        });
        quillInstances = [];
    }

    function initQuillEditors(container) {
        if (typeof Quill === 'undefined') { return; }

        container = container || document;

        container.querySelectorAll('.zbp-quill-wrapper').forEach(function(wrapper) {
            if (initializedQuills && initializedQuills.has(wrapper)) { return; }
            if (initializedQuills) { initializedQuills.add(wrapper); }

            var editor = wrapper.querySelector('.zbp-quill-editor');
            var input = wrapper.querySelector('.zbp-quill-value');
            var field = ZBP.getData(wrapper, 'field') || 'description';

            var quill = new Quill(editor, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, 4, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'link'],
                        ['clean']
                    ]
                },
                placeholder: ZBP.getData(wrapper, 'placeholder') || 'Describe in detail...'
            });

            if (input && input.value) {
                quill.root.innerHTML = input.value;
            }

            quill.on('text-change', function() {
                if (input) { input.value = quill.root.innerHTML; }
            });

            quillInstances.push({ quill: quill, wrapper: wrapper, field: field });
        });
    }

    function initJobsEditor(container) {
        container = container || document;
        var area = container.querySelector('#zeko-job-description');
        if (!area || typeof wp === 'undefined' || !wp.editor) { return; }
        if (window.tinyMCE && tinyMCE.get('zeko-job-description')) { return; }

        var settings = {};
        if (window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit &&
            window.tinyMCEPreInit.mceInit['zeko-job-description']) {
            settings = window.tinyMCEPreInit.mceInit['zeko-job-description'];
            settings.body_class = settings.body_class || '';
            settings.selector = '#zeko-job-description';
        } else {
            settings.theme = 'modern';
            settings.toolbar1 = 'bold,italic,underline,strike,bullist,numlist,blockquote,link';
            settings.toolbar2 = '';
            settings.toolbar3 = '';
            settings.height = 200;
        }

        var content = area.value;
        try {
            wp.editor.initialize('zeko-job-description', settings);
            tinyMCE.get('zeko-job-description').setContent(content);
        } catch (e) {
            // If programmatic init fails, leave the textarea (still serialized by FormData).
        }
    }

    function syncQuillValues() {
        quillInstances.forEach(function(item) {
            if (item.quill && item.wrapper) {
                var input = item.wrapper.querySelector('.zbp-quill-value');
                if (input) { input.value = item.quill.root.innerHTML; }
            }
        });
    }

    function reloadTab() {
        var active = document.querySelector('.zbp-portal-nav__item--active');
        if (active) {
            destroyQuills();
            active.click();
        }
    }

    /* ── Portal Tabs ────────────────────────────────────────── */

    function initPortalTabs() {
        var nav = document.querySelector('.zbp-portal-nav');
        var content = document.querySelector('.zbp-portal-content');

        if (!nav) { return; }

        nav.addEventListener('click', function(e) {
            var item = ZBP.closest(e.target, '.zbp-portal-nav__item');
            if (!item) { return; }
            e.preventDefault();
            var tab = ZBP.getData(item, 'tab');
            var businessId = ZBP.getData(item, 'business-id') || 0;

            nav.querySelectorAll('.zbp-portal-nav__item').forEach(function(n) {
                n.classList.remove('zbp-portal-nav__item--active');
            });
            item.classList.add('zbp-portal-nav__item--active');

            destroyQuills();
            if (content) { ZBP.showLoading(content); }

            ZBP.ajax('zbp_portal_tab_load', {
                tab: tab,
                business_id: businessId
            }).then(function(res) {
                if (content) { ZBP.hideLoading(content); }
                if (res.success) {
                    content.innerHTML = res.data;
                    initQuillEditors(content);
                    initJobsEditor(content);
                    fireQaContentLoaded();
                } else {
                    content.innerHTML = '<div class="zbp-empty-state">' + (res.data || 'Error loading tab') + '</div>';
                }
            }).catch(function() {
                if (content) { ZBP.hideLoading(content); }
                content.innerHTML = '<div class="zbp-empty-state">Failed to load tab content.</div>';
            });
        });

        var activeTab = nav.querySelector('.zbp-portal-nav__item--active');
        if (activeTab) {
            activeTab.click();
        }
    }

    /* ── Generic Form Submit ────────────────────────────────── */

    function initPortalForms() {
        ZBP.on('.zbp-portal-form', 'submit', function(e) {
            e.preventDefault();
            var form = this;
            var action = ZBP.getData(form, 'action');
            var btn = form.querySelector('button[type="submit"]');

            if (!action) { return; }

            syncQuillValues();
            syncActionButtons(form);

            if (btn) { btn.disabled = true; btn.classList.add('zbp-btn--loading'); }

            var data = {};
            var formData = new FormData(form);
            formData.forEach(function(value, name) {
                if (!(name in data)) { data[name] = value; }
            });

            ZBP.ajax(action, data).then(function(res) {
                if (res.success) {
                    ZBP.showAlert((res.data && res.data.message) || 'Saved!', 'success');
                    if (action === 'zbp_save_product' || action === 'zbp_delete_product' || action === 'zbp_create_service' || action === 'zbp_update_service' || action === 'zbp_delete_service') {
                        reloadTab();
                    }
                } else {
                    ZBP.showAlert(res.data || 'Error', 'error');
                }
                if (btn) { btn.disabled = false; btn.classList.remove('zbp-btn--loading'); }
            }).catch(function() {
                ZBP.showAlert('Network error', 'error');
                if (btn) { btn.disabled = false; btn.classList.remove('zbp-btn--loading'); }
            });
        });
    }

    /* ── Service Actions ────────────────────────────────────── */

    function initServiceActions() {
        ZBP.on('.zbp-service-edit', 'click', function() {
            var form = this.closest('.zbp-service-manage');
            var target = form ? form.querySelector('.zbp-service-manage__form') : null;
            var open = target ? !target.hasAttribute('hidden') : true;

            document.querySelectorAll('.zbp-service-manage__form').forEach(function(f) {
                setHidden(f, true);
            });
            if (!open && target) {
                setHidden(target, false);
                initQuillEditors(target);
                var nameField = target.querySelector('input[name="name"]');
                if (nameField) { nameField.focus(); }
            }
            this.setAttribute('aria-expanded', String(!open));
        });

        ZBP.on('.zbp-service-delete', 'click', function() {
            if (!window.confirm(ZBP.t('confirm_delete') || 'Are you sure?')) { return; }

            var row = this.closest('.zbp-service-manage');
            var businessField = row ? row.querySelector('[name="business_id"]') : null;
            ZBP.ajax('zbp_delete_service', {
                service_id: ZBP.getData(row, 'service-id'),
                business_id: businessField ? businessField.value : 0
            }).then(function(res) {
                ZBP.showAlert(res.success ? (res.data.message || 'Deleted.') : (res.data || 'Error'), res.success ? 'success' : 'error');
                if (res.success) { reloadTab(); }
            });
        });

        ZBP.on('.zbp-service-toggle', 'click', function() {
            var btn = this;
            var row = btn.closest('.zbp-service-manage');
            var businessField = row ? row.querySelector('[name="business_id"]') : null;
            ZBP.ajax('zbp_toggle_service', {
                service_id: ZBP.getData(row, 'service-id'),
                business_id: businessField ? businessField.value : 0
            }).then(function(res) {
                if (res.success) {
                    btn.setAttribute('aria-pressed', res.data.is_active ? 'true' : 'false');
                    btn.textContent = res.data.is_active ? ZBP.t('active') : ZBP.t('inactive');
                    ZBP.showAlert(res.data.message || 'Updated.', 'success');
                } else {
                    ZBP.showAlert(res.data || 'Error', 'error');
                }
            });
        });
    }

    /* ── Category Add (Service + Product) ────────────────────── */

    function initServiceCategoryAdd() {
        ZBP.on('.zbp-cat-add__btn, .zbp-cat-add__btn--product', 'click', function() {
            var btn = this;
            var add = btn.closest('.zbp-cat-add');
            var input = add ? add.querySelector('.zbp-cat-add__input') : null;
            var name = input ? input.value.replace(/^\s+|\s+$/g, '') : '';
            var form = btn.closest('.zbp-portal-form');
            var select = form ? form.querySelector('select[name="category"]') : null;
            var businessField = form ? form.querySelector('[name="business_id"]') : null;
            var businessId = businessField && businessField.value ? businessField.value : 0;
            var isProduct = btn.classList.contains('zbp-cat-add__btn--product');
            var action = isProduct ? 'zbp_add_product_category' : 'zbp_add_service_category';

            if (!name) {
                if (input) { input.focus(); }
                return;
            }

            btn.disabled = true;

            ZBP.ajax(action, {
                business_id: businessId,
                name: name
            }).then(function(res) {
                btn.disabled = false;
                if (res.success) {
                    var exists = false;
                    if (select) {
                        Array.prototype.forEach.call(select.options, function(opt) {
                            if (opt.value === String(res.data.slug)) { exists = true; }
                        });
                        if (!exists) {
                            var option = document.createElement('option');
                            option.value = res.data.slug;
                            option.textContent = res.data.name;
                            select.appendChild(option);
                        }
                        select.value = res.data.slug;
                    }
                    if (input) { input.value = ''; }
                    ZBP.showAlert(res.data.message || 'Category added.', 'success');
                } else {
                    ZBP.showAlert(res.data || 'Error', 'error');
                }
            }).catch(function() {
                btn.disabled = false;
                ZBP.showAlert('Network error', 'error');
            });
        });
    }

    /* ── Product Actions ────────────────────────────────────── */

    function initProductActions() {
        ZBP.on('.zbp-edit-shop-product', 'click', function() {
            var productId = ZBP.getData(this, 'product-id');
            var card = this.closest('.zbp-product-card');

            var form = document.querySelector('.zbp-product-form');
            if (!form) { return; }

            var titleInput = form.querySelector('[name="product_id"]');
            if (titleInput) { titleInput.value = productId; }
            var titleField = form.querySelector('[name="title"]');
            var cardTitle = card ? card.querySelector('.zbp-product-card__title') : null;
            if (titleField && cardTitle) { titleField.value = cardTitle.textContent; }

            var top = form.getBoundingClientRect().top + window.pageYOffset - 80;
            window.scrollTo({ top: top, behavior: 'smooth' });

            if (titleField) { titleField.focus(); }
        });

        ZBP.on('.zbp-delete-shop-product', 'click', function() {
            if (!window.confirm(ZBP.t('confirm_delete') || 'Are you sure?')) { return; }

            var productId = ZBP.getData(this, 'product-id');
            var firstBiz = document.querySelector('[name="business_id"]');
            var businessId = firstBiz ? firstBiz.value : 0;

            ZBP.ajax('zbp_delete_product', {
                business_id: businessId,
                product_id: productId
            }).then(function(res) {
                ZBP.showAlert(res.data.message || 'Done', res.success ? 'success' : 'error');
                if (res.success) { reloadTab(); }
            });
        });

        ZBP.on('.zbp-cancel-edit', 'click', function() {
            var form = this.closest('.zbp-product-form');
            if (!form) { return; }
            ['product_id', 'title', 'short_description', 'price', 'sale_price', 'sku', 'category', 'tags', 'image_url', 'gallery_urls', 'file_url'].forEach(function(name) {
                var el = form.querySelector('[name="' + name + '"]');
                if (el) { el.value = ''; }
            });
            var stock = form.querySelector('[name="stock"]');
            if (stock) { stock.value = '-1'; }
            var status = form.querySelector('[name="status"]');
            if (status) { status.value = 'active'; }

            form.querySelectorAll('.zbp-media-preview-img').forEach(function(i) { i.remove(); });
            form.querySelectorAll('.zbp-media-placeholder').forEach(function(p) { p.style.display = ''; });
            form.querySelectorAll('.zbp-gallery-item').forEach(function(i) { i.remove(); });

            quillInstances.forEach(function(item) {
                if (item.field === 'description') {
                    item.quill.setText('');
                }
            });
        });
    }

    /* ── Media Upload (Product Image + Gallery) ─────────────── */

    function initProductMediaUpload() {
        if (typeof wp === 'undefined' || !wp.media) { return; }

        // Product image select
        ZBP.on('.zbp-product-form .zbp-media-select', 'click', function(e) {
            e.preventDefault();
            var section = this.closest('.zbp-product-form__image-section');
            var form = this.closest('form');

            var frame = wp.media({
                title: 'Select Product Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                var imageUrl = form.querySelector('[name="image_url"]');
                if (imageUrl) { imageUrl.value = attachment.url; }
                if (section) {
                    section.querySelectorAll('.zbp-media-placeholder').forEach(function(p) { p.style.display = 'none'; });
                    section.querySelectorAll('.zbp-media-preview-img').forEach(function(i) { i.remove(); });
                    var upload = section.querySelector('.zbp-media-upload');
                    if (upload) { upload.insertAdjacentHTML('beforeend', '<img src="' + url + '" class="zbp-media-preview-img" />'); }
                    section.querySelectorAll('.zbp-media-remove').forEach(function(r) { r.style.display = ''; });
                }
            });

            frame.open();
        });

        // Product image remove
        ZBP.on('.zbp-product-form .zbp-media-remove', 'click', function(e) {
            e.preventDefault();
            var section = this.closest('.zbp-product-form__image-section');
            var form = this.closest('form');

            var imageUrl = form.querySelector('[name="image_url"]');
            if (imageUrl) { imageUrl.value = ''; }
            if (section) {
                section.querySelectorAll('.zbp-media-preview-img').forEach(function(i) { i.remove(); });
                section.querySelectorAll('.zbp-media-placeholder').forEach(function(p) { p.style.display = ''; });
            }
            this.style.display = 'none';
        });

        // Gallery add
        ZBP.on('.zbp-product-form .zbp-gallery-add', 'click', function(e) {
            e.preventDefault();
            var section = this.closest('.zbp-product-form__gallery-section');
            var grid = section ? section.querySelector('.zbp-gallery-grid') : null;
            var form = this.closest('form');
            var current = [];
            var galleryField = form.querySelector('[name="gallery_urls"]');
            try { current = JSON.parse((galleryField && galleryField.value) || '[]'); } catch (ex) { current = []; }

            var frame = wp.media({
                title: 'Add Gallery Images',
                button: { text: 'Add to Gallery' },
                multiple: true,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                frame.state().get('selection').each(function(attachment) {
                    var a = attachment.toJSON();
                    var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
                    current.push(a.url);
                    if (grid) {
                        grid.insertAdjacentHTML('beforeend',
                            '<div class="zbp-gallery-item">' +
                            '<img src="' + url + '" />' +
                            '<button type="button" class="zbp-gallery-remove">&times;</button>' +
                            '</div>'
                        );
                    }
                });
                if (galleryField) { galleryField.value = JSON.stringify(current); }
            });

            frame.open();
        });

        // Gallery remove
        ZBP.on('.zbp-gallery-remove', 'click', function() {
            var item = this.closest('.zbp-gallery-item');
            if (!item) { return; }
            var grid = item.closest('.zbp-gallery-grid');
            var form = item.closest('form');
            var galleryField = form ? form.querySelector('[name="gallery_urls"]') : null;
            var current = [];
            try { current = JSON.parse((galleryField && galleryField.value) || '[]'); } catch (ex) { current = []; }

            var idx = -1;
            if (grid) {
                Array.prototype.forEach.call(grid.querySelectorAll('.zbp-gallery-item'), function(el, i) {
                    if (el === item) { idx = i; }
                });
            }
            if (idx >= 0 && idx < current.length) {
                current.splice(idx, 1);
            }
            if (galleryField) { galleryField.value = JSON.stringify(current); }
            item.remove();
        });
    }

    /* ── Service Image Upload ───────────────────────────────── */

    function initServiceImageUpload() {
        if (typeof wp === 'undefined' || !wp.media) { return; }

        ZBP.on('.zbp-upload-service-img', 'click', function(e) {
            e.preventDefault();
            var form = this.closest('form');
            var frame = wp.media({
                title: 'Select Service Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var imageId = form.querySelector('[name="image_id"]');
                if (imageId) { imageId.value = attachment.id; }
                var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                var preview = form.querySelector('#zbp-svc-image-preview');
                if (preview) {
                    preview.innerHTML = '<img src="' + url + '" alt="" />';
                } else if (!form.querySelector('.zbp-service-image-preview')) {
                    var uploadBtn = form.querySelector('.zbp-upload-service-img');
                    if (uploadBtn) {
                        uploadBtn.insertAdjacentHTML('afterend', '<span class="zbp-service-image-preview" style="margin-left:0.5rem"><img src="' + url + '" style="height:32px;border-radius:4px;vertical-align:middle" /></span>');
                    }
                } else {
                    var img = form.querySelector('.zbp-service-image-preview img');
                    if (img) { img.setAttribute('src', url); }
                }
            });
            frame.open();
        });
    }

    /* ── Media Picker (Business Photos) ─────────────────────── */

    function initMediaPicker() {
        var button = document.getElementById('zbp-media-picker');
        if (!button || typeof wp === 'undefined' || !wp.media) { return; }

        var frame;

        button.addEventListener('click', function(e) {
            e.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: ZBP.t('select_images') || 'Select images',
                    multiple: true,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    var businessIdField = document.getElementById('zbp-media-business-id');
                    var businessId = businessIdField ? businessIdField.value : 0;
                    var selection = frame.state().get('selection');
                    var pending = selection.length;
                    var added = 0;

                    selection.each(function(attachment) {
                        ZBP.ajax('zbp_add_media', {
                            business_id: businessId,
                            attachment_id: attachment.id
                        }).then(function(res) {
                            added += res.success ? 1 : 0;
                            pending--;
                            if (!pending) {
                                ZBP.showAlert(added > 0 ? (added + ' photo(s) added.') : 'Could not add photos.', added > 0 ? 'success' : 'error');
                                reloadTab();
                            }
                        }).catch(function() {
                            pending--;
                            if (!pending) {
                                ZBP.showAlert('Could not add photos.', 'error');
                                reloadTab();
                            }
                        });
                    });
                });
            }

            frame.open();
        });
    }

    function initMediaDelete() {
        ZBP.on('.zbp-media-delete', 'click', function() {
            var btn = this;
            var businessIdField = document.getElementById('zbp-media-business-id');
            ZBP.ajax('zbp_delete_media', {
                media_id: ZBP.getData(btn, 'media-id'),
                business_id: businessIdField ? businessIdField.value : 0
            }).then(function(res) {
                ZBP.showAlert(res.success ? (res.data.message || 'Removed.') : (res.data || 'Error'), res.success ? 'success' : 'error');
                if (res.success) {
                    var item = btn.closest('.zbp-media-grid__item');
                    if (item) { item.remove(); }
                }
            });
        });
    }

    /* ── Business Switch ────────────────────────────────────── */

    function initBusinessSwitch() {
        ZBP.on('.zbp-biz-switch', 'click', function() {
            var businessId = ZBP.getData(this, 'business-id');
            document.querySelectorAll('.zbp-portal-nav__item').forEach(function(n) {
                n.setAttribute('data-business-id', businessId);
            });
            reloadTab();
        });
    }

    /* ── Hours Copy ─────────────────────────────────────────── */

    function initHoursCopy() {
        ZBP.on('.zbp-hours-copy', 'click', function() {
            var form = this.closest('form');
            var table = form ? form.querySelector('.zbp-hours-editor tbody') : null;
            if (!table) { return; }
            var first = table.querySelector('tr[data-day="0"]');

            ['open', 'close'].forEach(function(field) {
                var firstInput = first ? first.querySelector('[name^="hours[0][' + field + ']"]') : null;
                var value = firstInput ? firstInput.value : '';
                table.querySelectorAll('tr').forEach(function(row) {
                    if (row === first) { return; }
                    row.querySelectorAll('[name$="[' + field + ']"]').forEach(function(input) {
                        input.value = value;
                    });
                });
            });

            ZBP.showAlert(ZBP.t('copied_to_all') || 'Copied to all days.', 'info');
        });
    }

    /* ── Invite Tabs ────────────────────────────────────────── */

    function initInviteTabs() {
        ZBP.on('.zbp-invite-tab', 'click', function() {
            var tab = ZBP.getData(this, 'invite-tab');
            document.querySelectorAll('.zbp-invite-tab').forEach(function(t) {
                t.classList.remove('zbp-invite-tab--active');
            });
            this.classList.add('zbp-invite-tab--active');
            document.querySelectorAll('.zbp-invite-form').forEach(function(f) {
                setHidden(f, true);
            });
            document.querySelectorAll('.zbp-invite-form[data-invite-panel="' + tab + '"]').forEach(function(f) {
                setHidden(f, false);
            });
        });
    }

    function initInviteActions() {
        ZBP.on('.zbp-revoke-invite', 'click', function() {
            if (!window.confirm('Revoke this invitation?')) { return; }
            var btn = this;
            ZBP.ajax('zbp_revoke_invite', {
                invite_id: ZBP.getData(btn, 'invite-id')
            }).then(function(res) {
                ZBP.showAlert(res.data.message || 'Done', res.success ? 'success' : 'error');
                if (res.success) { reloadTab(); }
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
            });
        });

        ZBP.on('.zbp-resend-invite', 'click', function() {
            var btn = this;
            btn.disabled = true;
            ZBP.ajax('zbp_resend_invite', {
                invite_id: ZBP.getData(btn, 'invite-id')
            }).then(function(res) {
                ZBP.showAlert(res.data.message || 'Done', res.success ? 'success' : 'error');
                btn.disabled = false;
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
                btn.disabled = false;
            });
        });
    }

    /* ── Upgrade ────────────────────────────────────────────── */

    ZBP.on('.zbp-upgrade-btn', 'click', function() {
        var btn = this;
        var businessId = ZBP.getData(btn, 'business-id');
        var newPlan = ZBP.getData(btn, 'new-plan');

        if (!window.confirm('Upgrade this business to ' + newPlan + '?')) { return; }

        btn.disabled = true;
        btn.textContent = 'Processing...';

        ZBP.ajax('zbp_upgrade_plan', {
            business_id: businessId,
            new_plan: newPlan
        }).then(function(res) {
            if (res.success) {
                ZBP.showAlert(res.data.message || 'Upgrade successful!', 'success');
                btn.textContent = 'Upgraded';
                var currentTab = document.querySelector('.zbp-portal-nav__item--active');
                if (currentTab) { currentTab.click(); }
            } else {
                ZBP.showAlert(res.data || 'Payment failed', 'error');
                btn.disabled = false;
                btn.textContent = 'Retry Upgrade';
            }
        }).catch(function() {
            ZBP.showAlert('Network error', 'error');
            btn.disabled = false;
            btn.textContent = 'Retry Upgrade';
        });
    });

    /* ── Action Buttons Repeater ────────────────────────────── */

    function syncActionButtons(form) {
        var repeater = form ? form.querySelector('[data-zbp-repeater]') : null;
        if (!repeater) { return; }
        var hidden = form.querySelector('[name="action_buttons"]');
        if (!hidden) { return; }

        var types = [ 'call', 'whatsapp', 'email', 'contact', 'website', 'video', 'signup', 'start_order', 'view_shop', 'get_tickets' ];
        var options = types.map(function(t) { return '<option value="' + t + '">' + t.replace(/_/g, ' ') + '</option>'; }).join('');

        var buttons = [];
        repeater.querySelectorAll('[data-zbp-repeater] > .zbp-portal-form__row').forEach(function(row) {
            var typeEl = row.querySelector('[data-f-type]');
            var labelEl = row.querySelector('[data-f-label]');
            var valueEl = row.querySelector('[data-f-value]');
            var value = valueEl ? valueEl.value.trim() : '';
            if (!typeEl || !value) { return; }
            buttons.push({
                type: typeEl.value,
                label: labelEl ? labelEl.value.trim() : '',
                value: value
            });
        });
        hidden.value = JSON.stringify(buttons);
    }

    function addActionButtonRow(repeater) {
        var types = [ 'call', 'whatsapp', 'email', 'contact', 'website', 'video', 'signup', 'start_order', 'view_shop', 'get_tickets' ];
        var options = types.map(function(t) { return '<option value="' + t + '">' + t.replace(/_/g, ' ') + '</option>'; }).join('');
        var row = document.createElement('div');
        row.className = 'zbp-portal-form__row';
        row.style.cssText = 'grid-template-columns:9rem 1fr 1fr auto;display:grid;gap:0.5rem;margin-bottom:0.5rem';
        row.innerHTML =
            '<select class="zbp-form-input" data-f-type>' + options + '</select>' +
            '<input type="text" class="zbp-form-input" data-f-label placeholder="Label (optional)" />' +
            '<input type="text" class="zbp-form-input" data-f-value placeholder="Value / URL" />' +
            '<button type="button" class="button" data-zbp-remove>&times;</button>';
        repeater.appendChild(row);
    }

    ZBP.on('.zbp-portal-form', 'submit', function(e) {
        syncActionButtons(this);
    });

    ZBP.on('[data-zbp-add-action]', 'click', function() {
        var repeater = this.previousElementSibling;
        if (repeater && repeater.getAttribute('data-zbp-repeater') !== null) {
            addActionButtonRow(repeater);
            var hidden = this.parentElement.querySelector('[name="action_buttons"]');
            if (hidden) { hidden.value = ''; }
        }
    });

    ZBP.on('[data-zbp-remove]', 'click', function() {
        var row = this.closest('.zbp-portal-form__row');
        if (row) { row.remove(); }
    });

    ZBP.on('.zbp-portal-form [data-f-type], .zbp-portal-form [data-f-label], .zbp-portal-form [data-f-value]', 'change', function() {
        var form = ZBP.closest(this, '.zbp-portal-form');
        if (form) { syncActionButtons(form); }
    });

    /* ── Hours Mode Toggle ────────────────────────────────── */

    function initHoursMode() {
        ZBP.on('[data-hours-mode]', 'change', function() {
            var form = ZBP.closest(this, '.zbp-portal-form');
            var schedule = form ? form.querySelector('[data-hours-schedule]') : null;
            if (!schedule) { return; }
            var v = this.value || '';
            setHidden(schedule, !(v === '' || v === 'regular'));
        });
    }

})();
