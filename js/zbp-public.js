/* global ZBP, Quill, zbpPublic */
(function() {
    'use strict';

    ZBP.ready(function() {
        initTabNavigation();
        initFollowButtons();
        initReviewForm();
        initReviewPhotos();
        initHelpfulVotes();
        initStarRating();
        initCopyLink();
        initAjaxForms();
        initWizard();
        initPaymentModal();
    });

    function setHidden(el, hidden) {
        if (!el) return;
        if (hidden) { el.setAttribute('hidden', ''); } else { el.removeAttribute('hidden'); }
    }

    /**
     * Multi-step wizard for the business submission form. Panels live in
     * fieldsets[data-step]; navigation is purely client-side and the whole
     * form still submits as one request for no-JS fallback.
     */
    function initWizard() {
        var wizard = document.querySelector('.zbp-wizard');
        if (!wizard) return;

        var steps = wizard.querySelectorAll('.zbp-wizard__step');
        var panels = wizard.querySelectorAll('.zbp-wizard__panel');
        var nextBtn = wizard.querySelector('.zbp-wizard__next');
        var prevBtn = wizard.querySelector('.zbp-wizard__prev');
        var current = 1;
        var total = panels.length;
        var quillInstance = null;

        function show(step) {
            current = Math.min(Math.max(step, 1), total);

            wizard.querySelectorAll('.zbp-wizard__panel').forEach(function(panel) {
                var active = Number(ZBP.getData(panel, 'step')) === current;
                setHidden(panel, !active);
            });

            wizard.querySelectorAll('.zbp-wizard__step').forEach(function(stepEl) {
                var lit = Number(ZBP.getData(stepEl, 'step-label')) <= current;
                stepEl.classList.toggle('zbp-wizard__step--active', lit);
            });

            setHidden(prevBtn, current === 1);
            if (nextBtn) {
                nextBtn.textContent = current === total ? (ZBP.t('submit') || 'Submit') : (ZBP.t('next') || 'Next');
                setHidden(nextBtn, current === total);
            }

            if (current === 4 && !quillInstance && typeof Quill !== 'undefined') {
                quillInstance = new Quill('#zbp-quill-desc', {
                    theme: 'snow',
                    placeholder: 'Tell people about your business...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                var ta = wizard.querySelector('#zbp-cb-desc');
                if (ta && ta.value) {
                    quillInstance.root.innerHTML = ta.value;
                }
                quillInstance.on('text-change', function() {
                    if (ta) { ta.value = quillInstance.root.innerHTML; }
                });
            }

            if (current === 5) {
                buildReviewSummary();
            }

            wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function buildReviewSummary() {
            var summary = document.getElementById('zbp-review-summary');
            if (!summary) return;
            var html = '<table class="zbp-review-summary__table"><tbody>';

            function getVal(name) {
                var el = wizard.querySelector('[name="' + name + '"]');
                return el ? el.value : '';
            }
            function getCheckedCats() {
                var cats = [];
                wizard.querySelectorAll('[name="categories[]"]:checked').forEach(function(cb) {
                    var next = cb.nextElementSibling;
                    if (next) { cats.push(next.textContent); }
                });
                return cats;
            }

            var name = getVal('business_name');
            var tagline = getVal('tagline');
            var shortDesc = getVal('short_description');
            var cats = getCheckedCats();
            var address = getVal('address');
            var city = getVal('city');
            var state = getVal('state');
            var country = getVal('country');
            var phone = getVal('phone');
            var email = getVal('email');
            var website = getVal('website');
            var founded = getVal('founding_year');
            var employees = getVal('employee_count');
            var desc = getVal('zbp-cb-desc');

            function row(label, value) {
                if (!value) return '';
                return '<tr><th>' + ZBP.escapeHtml(label) + '</th><td>' + ZBP.escapeHtml(value) + '</td></tr>';
            }

            html += row('Name', name);
            html += row('Tagline', tagline);
            if (cats.length) html += row('Categories', cats.join(', '));
            html += row('Short Description', shortDesc);
            html += row('Address', [address, city, state, country].filter(Boolean).join(', '));
            html += row('Phone', phone);
            html += row('Email', email);
            html += row('Website', website);
            html += row('Founded', founded);
            html += row('Team Size', employees);
            if (desc && desc !== '<p><br></p>') {
                html += '<tr><th>Description</th><td>' + ZBP.escapeHtml(desc.substring(0, 200) + (desc.length > 200 ? '...' : '')) + '</td></tr>';
            }
            html += '</tbody></table>';
            summary.innerHTML = html;
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                var valid = true;
                wizard.querySelector('.zbp-wizard__panel[data-step="' + current + '"]')
                    .querySelectorAll('input[required], select[required], textarea[required]')
                    .forEach(function(field) {
                        if (!field.checkValidity()) {
                            field.reportValidity();
                            valid = false;
                        }
                    });
                if (valid) { show(current + 1); }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                show(current - 1);
            });
        }

        wizard.addEventListener('submit', function() {
            var socials = collectSocialLinks();
            var socialField = wizard.querySelector('[name="social_links"]');
            if (socialField) { socialField.value = JSON.stringify(socials); }
            if (quillInstance) {
                var descField = wizard.querySelector('#zbp-cb-desc');
                if (descField) { descField.value = quillInstance.root.innerHTML; }
            }
            syncMediaFields();
        });

        function collectSocialLinks() {
            var links = {};
            wizard.querySelectorAll('[data-social]').forEach(function(input) {
                var platform = ZBP.getData(input, 'social');
                var val = input.value.replace(/^\s+|\s+$/g, '');
                if (val) { links[platform] = val; }
            });
            return links;
        }

        function syncMediaFields() {
            var avatarImg = document.querySelector('#zbp-avatar-preview img');
            var coverImg = document.querySelector('#zbp-cover-preview img');
            if (avatarImg) {
                var avatarField = wizard.querySelector('[name="avatar_id"]');
                if (avatarField) { avatarField.value = parseInt(ZBP.getData(avatarImg, 'id'), 10) || 0; }
            }
            if (coverImg) {
                var coverField = wizard.querySelector('[name="cover_id"]');
                if (coverField) { coverField.value = parseInt(ZBP.getData(coverImg, 'id'), 10) || 0; }
            }
            var gids = [];
            wizard.querySelectorAll('.zbp-wizard-gallery .zbp-gallery-item').forEach(function(item) {
                var id = parseInt(ZBP.getData(item, 'media-id'), 10);
                if (id) { gids.push(id); }
            });
            var galleryField = wizard.querySelector('[name="gallery_ids"]');
            if (galleryField) { galleryField.value = gids.join(','); }
            var lat = document.getElementById('zbp-cb-lat');
            var lng = document.getElementById('zbp-cb-lng');
            var latField = wizard.querySelector('[name="lat"]');
            var lngField = wizard.querySelector('[name="lng"]');
            if (latField) { latField.value = lat ? lat.value : ''; }
            if (lngField) { lngField.value = lng ? lng.value : ''; }
        }

        if (typeof wp !== 'undefined' && wp.media) {
            initMediaUploader('.zbp-upload-avatar-btn', '#zbp-avatar-preview', '#zbp-avatar-upload .zbp-remove-avatar-btn', function(attachment) {
                var field = wizard.querySelector('[name="avatar_id"]');
                if (field) { field.value = attachment.id; }
            });
            initMediaUploader('.zbp-upload-cover-btn', '#zbp-cover-preview', '#zbp-cover-upload .zbp-remove-avatar-btn', function(attachment) {
                var field = wizard.querySelector('[name="cover_id"]');
                if (field) { field.value = attachment.id; }
            });
            initWizardGallery();
        }

        function initWizardGallery() {
            wizard.addEventListener('click', function(e) {
                if (ZBP.closest(e.target, '.zbp-wizard-gallery-add')) {
                    e.preventDefault();
                    var frame = wp.media({
                        title: ZBP.t('select_images') || 'Select images',
                        button: { text: 'Add to Gallery' },
                        multiple: true,
                        library: { type: 'image' }
                    });
                    frame.on('select', function() {
                        frame.state().get('selection').each(function(attachment) {
                            var a = attachment.toJSON();
                            var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
                            var gallery = wizard.querySelector('.zbp-wizard-gallery');
                            if (gallery) {
                                gallery.insertAdjacentHTML('beforeend',
                                    '<div class="zbp-gallery-item" data-media-id="' + a.id + '">' +
                                    '<img src="' + url + '" alt="" />' +
                                    '<button type="button" class="zbp-gallery-remove">&times;</button>' +
                                    '</div>'
                                );
                            }
                        });
                        syncMediaFields();
                    });
                    frame.open();
                } else if (ZBP.closest(e.target, '.zbp-gallery-remove')) {
                    var item = ZBP.closest(e.target, '.zbp-gallery-item');
                    if (item) { item.remove(); }
                    syncMediaFields();
                }
            });
        }

        wizard.querySelectorAll('.zbp-geolocate-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    ZBP.showAlert('Geolocation not supported', 'error');
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Locating...';
                navigator.geolocation.getCurrentPosition(function(pos) {
                    var lat = document.getElementById('zbp-cb-lat');
                    var lng = document.getElementById('zbp-cb-lng');
                    if (lat) { lat.value = pos.coords.latitude.toFixed(7); }
                    if (lng) { lng.value = pos.coords.longitude.toFixed(7); }
                    btn.disabled = false;
                    btn.textContent = 'Use My Location';
                }, function() {
                    ZBP.showAlert('Could not get location', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Use My Location';
                });
            });
        });

        wizard.querySelectorAll('.zbp-remove-avatar-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var preview = document.getElementById('zbp-avatar-preview');
                if (preview) { preview.innerHTML = '<span class="zbp-media-upload__placeholder">Click to upload logo</span>'; }
                var field = wizard.querySelector('[name="avatar_id"]');
                if (field) { field.value = 0; }
                setHidden(btn, true);
            });
        });

        wizard.querySelectorAll('.zbp-remove-cover-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var preview = document.getElementById('zbp-cover-preview');
                if (preview) { preview.innerHTML = '<span class="zbp-media-upload__placeholder">Click to upload cover photo</span>'; }
                var field = wizard.querySelector('[name="cover_id"]');
                if (field) { field.value = 0; }
                setHidden(btn, true);
            });
        });
    }

    function initMediaUploader(triggerSelector, previewSelector, removeBtnSelector, onSelect) {
        ZBP.on(triggerSelector, 'click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use This Image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                var preview = document.querySelector(previewSelector);
                if (preview) {
                    preview.innerHTML = '<img src="' + url + '" data-id="' + attachment.id + '" style="max-width:100%;border-radius:8px" />';
                }
                var removeBtn = document.querySelector(removeBtnSelector);
                if (removeBtn) { removeBtn.removeAttribute('hidden'); }
                if (onSelect) { onSelect(attachment); }
            });
            frame.open();
        });
    }

    function initTabNavigation() {
        var tabs = document.querySelectorAll('.zbp-single-tab');
        var contents = document.querySelectorAll('.zbp-single-tab-content');

        if (!tabs.length) return;

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = ZBP.getData(tab, 'tab');
                tabs.forEach(function(t) {
                    t.classList.remove('zbp-single-tab--active');
                    t.setAttribute('aria-selected', 'false');
                });
                contents.forEach(function(c) {
                    c.classList.remove('zbp-single-tab-content--active');
                });
                tab.classList.add('zbp-single-tab--active');
                tab.setAttribute('aria-selected', 'true');
                var content = document.getElementById(target);
                if (content) { content.classList.add('zbp-single-tab-content--active'); }
            });
        });

        var hasActive = Array.prototype.some.call(tabs, function(t) {
            return t.classList.contains('zbp-single-tab--active');
        });
        if (!hasActive && tabs[0]) {
            tabs[0].click();
        }
    }

    function initCopyLink() {
        ZBP.on('.zbp-copy-link', 'click', function(e) {
            e.preventDefault();
            var url = ZBP.getData(this, 'url') || window.location.href;

            var done = function() { ZBP.showAlert(ZBP.t('copied'), 'success'); };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done);
            } else {
                var input = document.createElement('input');
                input.value = url;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                done();
            }
        });
    }

    /**
     * Generic handler for small AJAX forms (claims, verification requests)
     * that only need a success/error message.
     */
    function initAjaxForms() {
        ZBP.on('.zbp-ajax-form', 'submit', function(e) {
            e.preventDefault();
            var form = this;
            var action = ZBP.getData(form, 'action');
            var btn = form.querySelector('button[type="submit"]');

            if (!action) return;

            btn.disabled = true;

            var data = {};
            var formData = new FormData(form);
            formData.forEach(function(value, name) {
                if (!(name in data)) { data[name] = value; }
            });

            ZBP.ajax(action, data).then(function(res) {
                ZBP.showAlert(
                    res && res.success ? ((res.data && res.data.message) || 'Submitted!') : (res.data || ZBP.t('error')),
                    res && res.success ? 'success' : 'error'
                );
                if (res && res.success) {
                    form.reset();
                }
                btn.disabled = false;
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
                btn.disabled = false;
            });
        });
    }

    function initFollowButtons() {
        ZBP.on('.zbp-follow-btn', 'click', function(e) {
            e.preventDefault();
            var btn = this;
            var businessId = ZBP.getData(btn, 'business-id');
            var isFollowing = btn.classList.contains('zbp-follow-btn--following');
            var action = isFollowing ? 'zbp_unfollow' : 'zbp_follow';

            ZBP.ajax(action, { business_id: businessId }).then(function(res) {
                if (res.success) {
                    if (isFollowing) {
                        btn.classList.remove('zbp-follow-btn--following');
                        btn.textContent = ZBP.t('follow');
                    } else {
                        btn.classList.add('zbp-follow-btn--following');
                        btn.textContent = ZBP.t('unfollow');
                    }
                    if (res.data && res.data.follower_count !== undefined) {
                        document.querySelectorAll('[data-follower-count]').forEach(function(el) {
                            el.textContent = res.data.follower_count;
                        });
                    }
                } else {
                    ZBP.showAlert(res.data || 'Error', 'error');
                }
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
            });
        });
    }

    function initReviewForm() {
        ZBP.on('.zbp-review-form form', 'submit', function(e) {
            e.preventDefault();
            var form = this;
            var btn = form.querySelector('button[type="submit"]');

            function fieldVal(name) {
                var el = form.querySelector('[name="' + name + '"]');
                return el ? el.value : '';
            }

            var rating = fieldVal('rating');
            if (!rating || parseInt(rating, 10) < 1) {
                ZBP.showAlert('Please select a rating', 'error');
                return;
            }

            var criteria = {};
            form.querySelectorAll('[name^="criteria["]').forEach(function(el) {
                var v = parseInt(el.value, 10);
                if (v < 1 || v > 5) { return; }
                var label = el.name.replace(/^criteria\[/, '').replace(/\]$/, '');
                criteria[label] = v;
            });

            btn.disabled = true;

            ZBP.ajax('zbp_submit_review', {
                business_id: fieldVal('business_id'),
                rating: rating,
                title: fieldVal('title'),
                content: fieldVal('content'),
                criteria: criteria,
                photo_ids: parsePhotoIds(fieldVal('photo_ids')),
                zbp_form_ts: fieldVal('zbp_form_ts'),
                zbp_website_confirm: fieldVal('zbp_website_confirm')
            }).then(function(res) {
                if (res.success) {
                    ZBP.showAlert(res.data.message || 'Review submitted!', 'success');
                    form.reset();
                    form.querySelectorAll('.zbp-review-form__star').forEach(function(s) {
                        s.classList.remove('zbp-review-form__star--active');
                    });
                    form.querySelectorAll('[name^="criteria["]').forEach(function(el) { el.value = 0; });
                    var ratingField = form.querySelector('[name="rating"]');
                    if (ratingField) { ratingField.value = 0; }

                    if (res.data.review_html) {
                        var list = document.querySelector('.zbp-reviews-list');
                        if (list) {
                            list.insertAdjacentHTML('afterbegin', res.data.review_html);
                        } else {
                            var tabContent = ZBP.closest(form, '.zbp-single-tab-content');
                            if (tabContent) {
                                var empty = tabContent.querySelector('.zbp-empty-state');
                                if (empty) { empty.remove(); }
                            }
                            form.insertAdjacentHTML('afterend', '<div class="zbp-reviews-list">' + res.data.review_html + '</div>');
                        }
                    }
                    if (res.data.avg_rating !== undefined) {
                        document.querySelectorAll('[data-avg-rating]').forEach(function(el) {
                            el.textContent = res.data.avg_rating;
                        });
                    }
                    if (res.data.review_count !== undefined) {
                        document.querySelectorAll('[data-review-count]').forEach(function(el) {
                            el.textContent = res.data.review_count;
                        });
                    }
                } else {
                    ZBP.showAlert(res.data || 'Error submitting review', 'error');
                }
                btn.disabled = false;
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
                btn.disabled = false;
            });
        });
    }

    function parsePhotoIds(val) {
        if (!val) { return []; }
        return String(val).split(',').map(function(s) { return parseInt(s, 10); }).filter(function(n) { return n > 0; });
    }

    function initReviewPhotos() {
        if (typeof wp === 'undefined' || !wp.media) { return; }

        ZBP.on('.zbp-review-photo-btn', 'click', function(e) {
            e.preventDefault();
            var form = ZBP.closest(this, 'form');
            var hidden = form ? form.querySelector('[name="photo_ids"]') : null;
            var frame = wp.media({
                title: ZBP.t('select_images') || 'Select images',
                button: { text: 'Use Photos' },
                multiple: true,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var ids = [];
                var selection = frame.state().get('selection');
                selection.each(function(attachment) {
                    ids.push(attachment.toJSON().id);
                });
                if (hidden) { hidden.value = ids.join(','); }
                var preview = form ? form.querySelector('.zbp-review-photos') : null;
                if (preview) {
                    preview.innerHTML = '';
                    preview.style.display = 'flex';
                    selection.each(function(attachment) {
                        var a = attachment.toJSON();
                        var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
                        preview.insertAdjacentHTML('beforeend', '<img src="' + url + '" alt="" class="zbp-review-photo" />');
                    });
                }
            });
            frame.open();
        });
    }

    function initHelpfulVotes() {
        ZBP.on('.zbp-helpful-btn', 'click', function(e) {
            e.preventDefault();
            var btn = this;
            var reviewId = ZBP.getData(btn, 'review-id');
            var value = parseInt(ZBP.getData(btn, 'value'), 10);

            ZBP.ajax('zbp_review_vote', {
                review_id: reviewId,
                value: value
            }).then(function(res) {
                if (res.success) {
                    var card = ZBP.closest(btn, '.zbp-review-card');
                    if (card) {
                        var helpful = card.querySelector('.zbp-helpful-btn[data-value="1"]');
                        var down = card.querySelector('.zbp-helpful-btn[data-value="-1"]');
                        if (helpful) {
                            helpful.classList.toggle('zbp-helpful-btn--active', res.data.user_vote === 1);
                            helpful.setAttribute('aria-pressed', res.data.user_vote === 1 ? 'true' : 'false');
                        }
                        if (down) {
                            down.classList.toggle('zbp-helpful-btn--active', res.data.user_vote === -1);
                            down.setAttribute('aria-pressed', res.data.user_vote === -1 ? 'true' : 'false');
                        }
                        if (helpful) {
                            var count = helpful.querySelector('.zbp-helpful-count');
                            if (count) { count.textContent = res.data.helpful_count; }
                        }
                    }
                } else {
                    ZBP.showAlert(res.data || 'Error', 'error');
                    if (res.data && /login/i.test(res.data)) {
                        window.location.href = window.location.href;
                    }
                }
            }).catch(function() {
                ZBP.showAlert(ZBP.t('error'), 'error');
            });
        });
    }

    function initStarRating() {
        ZBP.on('.zbp-review-form__star', 'click', function() {
            var star = this;
            var value = parseInt(ZBP.getData(star, 'value'), 10);
            var container = ZBP.closest(star, '.zbp-review-form__stars');

            container.querySelectorAll('.zbp-review-form__star').forEach(function(s) {
                if (parseInt(ZBP.getData(s, 'value'), 10) <= value) {
                    s.classList.add('zbp-review-form__star--active');
                } else {
                    s.classList.remove('zbp-review-form__star--active');
                }
            });

            var form = ZBP.closest(container, 'form');

            // Criteria star: persist into the labelled hidden input, then
            // recompute the overall rating as the average of all criteria.
            var criterion = ZBP.getData(container, 'criterion');
            if (criterion) {
                var hidden = form ? form.querySelector('[name="criteria[' + criterion + ']"]') : null;
                if (hidden) { hidden.value = value; }
                updateCriteriaRating(form);
                return;
            }

            var ratingField = form ? form.querySelector('[name="rating"]') : null;
            if (ratingField) { ratingField.value = value; }
        });
    }

    function updateCriteriaRating(form) {
        if (!form) { return; }
        var values = [];
        form.querySelectorAll('[name^="criteria["]').forEach(function(el) {
            var v = parseInt(el.value, 10);
            if (v >= 1 && v <= 5) { values.push(v); }
        });
        var ratingField = form.querySelector('[name="rating"]');
        if (!ratingField) { return; }
        if (values.length === 0) {
            ratingField.value = 0;
            return;
        }
        var sum = values.reduce(function(a, b) { return a + b; }, 0);
        ratingField.value = Math.max(1, Math.min(5, Math.round(sum / values.length)));
    }

    function initPaymentModal() {
        var modal = document.getElementById('zbp-payment-modal');
        if (!modal) { return; }
        var pendingAction = null;
        var pendingData = null;

        ZBP.on('.zbp-book-service, .zbp-buy-product', 'click', function() {
            var btn = this;
            var isService = btn.classList.contains('zbp-book-service');
            var name = ZBP.getData(btn, 'service-name') || ZBP.getData(btn, 'product-name');
            var price = parseFloat(ZBP.getData(btn, 'service-price') || ZBP.getData(btn, 'product-price'));
            var bizName = ZBP.getData(btn, 'business-name');
            var currency = ZBP.getData(btn, 'currency') || '$';
            var itemType = isService ? 'Service Booking' : 'Product Purchase';

            pendingAction = isService ? 'zbp_book_service' : 'zbp_buy_product';
            pendingData = {};
            if (isService) {
                pendingData.service_id = ZBP.getData(btn, 'service-id');
            } else {
                pendingData.product_id = ZBP.getData(btn, 'product-id');
            }
            pendingData.business_id = ZBP.getData(btn, 'business-id');

            var item = document.getElementById('zbp-payment-item');
            var business = document.getElementById('zbp-payment-business');
            var total = document.getElementById('zbp-payment-total');
            if (item) { item.textContent = itemType + ': ' + name; }
            if (business) { business.textContent = bizName; }
            if (total) { total.textContent = currency + price.toFixed(2); }

            setHidden(modal, false);
            var processing = modal.querySelector('#zbp-payment-processing');
            if (processing) { setHidden(processing, true); }
            var confirmBtn = modal.querySelector('#zbp-payment-confirm');
            var cancelBtn = modal.querySelector('#zbp-payment-cancel');
            if (confirmBtn) { confirmBtn.disabled = false; }
            if (cancelBtn) { cancelBtn.disabled = false; }
        });

        modal.querySelectorAll('.zbp-modal__close, .zbp-modal__overlay, #zbp-payment-cancel').forEach(function(el) {
            el.addEventListener('click', function() {
                setHidden(modal, true);
                pendingAction = null;
                pendingData = null;
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
                setHidden(modal, true);
                pendingAction = null;
                pendingData = null;
            }
        });

        var confirmBtn = modal.querySelector('#zbp-payment-confirm');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!pendingAction || !pendingData) { return; }

                confirmBtn.disabled = true;
                var cancelBtn = modal.querySelector('#zbp-payment-cancel');
                if (cancelBtn) { cancelBtn.disabled = true; }
                var processing = modal.querySelector('#zbp-payment-processing');
                if (processing) { setHidden(processing, false); }

                ZBP.ajax(pendingAction, {
                    service_id: pendingData.service_id || 0,
                    product_id: pendingData.product_id || 0,
                    business_id: pendingData.business_id
                }).then(function(res) {
                    setHidden(modal, true);
                    pendingAction = null;
                    pendingData = null;
                    ZBP.showAlert(res.data.message || 'Done', res.success ? 'success' : 'error');
                }).catch(function() {
                    setHidden(modal, true);
                    pendingAction = null;
                    pendingData = null;
                    ZBP.showAlert('Network error. Please try again.', 'error');
                });
            });
        }
    }

})();
