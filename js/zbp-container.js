/* global zbpPublic, zbpAdmin, ajaxurl */
window.ZBP = window.ZBP || {};
window.ZBP.version = '1.0.0';

ZBP.config = {};

ZBP.init = function() {
    ZBP.config = window.zbpPublic || window.zbpAdmin || {};
};

ZBP.ready = function(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
};

ZBP.ajax = function(action, data) {
    data = data || {};
    data.action = action;
    if (!ZBP.config.nonce) {
        ZBP.config = window.zbpPublic || window.zbpAdmin || {};
    }
    if (ZBP.config.nonce) {
        data._ajax_nonce = ZBP.config.nonce;
    }

    var url = ZBP.config.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';
    var body = new URLSearchParams();

    Object.keys(data).forEach(function(key) {
        var value = data[key];
        if (Array.isArray(value)) {
            value.forEach(function(entry) { body.append(key, entry); });
        } else if (value === null || value === undefined) {
            return;
        } else {
            body.append(key, value);
        }
    });

    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
    }).then(function(res) {
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        return res.json();
    });
};

ZBP.on = function(selector, event, handler) {
    var selectorMatch = function(node) { return node.matches && node.matches(selector); };
    document.addEventListener(event, function(e) {
        var el = e.target;
        while (el && el !== document) {
            if (selectorMatch(el)) {
                handler.call(el, e);
                return;
            }
            el = el.parentNode;
        }
    });
};

ZBP.closest = function(el, selector) {
    while (el && el !== document) {
        if (el.matches && el.matches(selector)) { return el; }
        el = el.parentNode;
    }
    return null;
};

ZBP.each = function(collection, fn) {
    Array.prototype.forEach.call(collection, fn);
};

ZBP.getData = function(el, key) {
    return el && el.getAttribute ? (el.getAttribute('data-' + key) || '') : '';
};

ZBP.setAttr = function(el, name, value) {
    if (el && el.setAttribute) {
        el.setAttribute(name, String(value));
    }
};

ZBP.showAlert = function(message, type) {
    type = type || 'info';
    var el = document.createElement('div');
    el.className = 'zbp-notification zbp-notification--' + type;
    el.textContent = message;
    var target = document.querySelector('.zbp-portal-content') || document.querySelector('.zbp-container') || document.body;
    target.insertBefore(el, target.firstChild);
    setTimeout(function() {
        el.style.transition = 'opacity 0.3s';
        el.style.opacity = '0';
        setTimeout(function() { el.remove(); }, 300);
    }, 3000);
};

ZBP.showLoading = function(element) {
    if (element) { element.classList.add('zbp-loading'); }
};

ZBP.hideLoading = function(element) {
    if (element) { element.classList.remove('zbp-loading'); }
};

ZBP.escapeHtml = function(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
};

ZBP.t = function(key) {
    return (ZBP.config.i18n && ZBP.config.i18n[key]) || key;
};

ZBP.ready(function() {
    ZBP.init();
});
