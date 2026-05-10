/**
 * Client-side search within a Twig-rendered page.
 *
 * Enable from Twig by including components/_navbar.html.twig with
 * navbar_page_search_enabled: true and a matching root + data-page-search-item nodes.
 *
 * Re-apply after dynamic DOM updates:
 *   document.dispatchEvent(new CustomEvent('page-search:refresh'));
 */
(function () {
    'use strict';

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function clearMarks(root, itemSelector) {
        if (!root) return;
        root.querySelectorAll(itemSelector).forEach(function (el) {
            el.classList.remove('page-search-match', 'page-search-dimmed');
            el.removeAttribute('data-page-search-hit-index');
        });
    }

    function isVisible(el) {
        if (!el) return false;
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }

    function initWrapper(wrapper) {
        if (wrapper.dataset.pageSearchInit === '1') return;
        wrapper.dataset.pageSearchInit = '1';

        var rootSel = wrapper.getAttribute('data-page-search-root');
        var itemSel = wrapper.getAttribute('data-page-search-item') || '[data-page-search-item]';
        var input = wrapper.querySelector('[data-page-search-input]');
        var btn = wrapper.querySelector('[data-page-search-submit]');
        var resultsEl = wrapper.querySelector('[data-page-search-results]');
        if (!input || !btn || !rootSel) return;

        function getRoot() {
            return document.querySelector(rootSel) || document.querySelector('main');
        }

        function getSearchableItems(root) {
            return Array.prototype.slice.call(root.querySelectorAll(itemSel)).filter(function (el) {
                if (!el || !isVisible(el)) return false;
                if (el.closest('.app-navbar')) return false;
                if (el.closest('.notification-panel')) return false;

                var tag = el.tagName ? el.tagName.toUpperCase() : '';
                if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') return false;

                return !!(el.textContent || '').trim();
            });
        }

        function setWrapperOpen(isOpen) {
            wrapper.classList.toggle('is-open', !!isOpen);
        }

        function closeSearchUi() {
            setWrapperOpen(false);
            if (resultsEl) {
                resultsEl.hidden = true;
                resultsEl.innerHTML = '';
            }
            clearMarks(getRoot(), itemSel);
        }

        function runSearch() {
            var root = getRoot();
            if (!root) return;

            var q = input.value.trim();
            clearMarks(root, itemSel);

            if (!resultsEl) return;
            resultsEl.innerHTML = '';
            resultsEl.hidden = true;
            setWrapperOpen(true);

            if (!q) return;

            var lower = q.toLowerCase();
            var items = getSearchableItems(root);
            var matches = [];

            items.forEach(function (el) {
                var text = (el.textContent || '').toLowerCase();
                if (text.indexOf(lower) !== -1) {
                    el.classList.add('page-search-match');
                    matches.push(el);
                } else {
                    el.classList.add('page-search-dimmed');
                }
            });

            resultsEl.hidden = false;

            if (matches.length === 0) {
                resultsEl.innerHTML = '<span class="page-search-results__empty">No matches on this page.</span>';
                return;
            }

            matches.forEach(function (el, i) {
                el.setAttribute('data-page-search-hit-index', String(i));
            });

            var listHtml = matches
                .map(function (el, i) {
                    var snippet = (el.textContent || '').trim().replace(/\s+/g, ' ');
                    if (snippet.length > 72) snippet = snippet.slice(0, 69) + '…';
                    return (
                        '<li class="page-search-results__li"><button type="button" class="page-search-results__jump" data-page-search-jump="' +
                        i +
                        '">' +
                        escapeHtml(snippet) +
                        '</button></li>'
                    );
                })
                .join('');

            resultsEl.innerHTML =
                '<div class="page-search-results__summary">' +
                matches.length +
                ' match' +
                (matches.length !== 1 ? 'es' : '') +
                '</div><ul class="page-search-results__ul">' +
                listHtml +
                '</ul>';

            resultsEl.querySelectorAll('[data-page-search-jump]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var idx = button.getAttribute('data-page-search-jump');
                    var target = root.querySelector('[data-page-search-hit-index="' + idx + '"]');
                    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            });
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!wrapper.classList.contains('is-open')) {
                setWrapperOpen(true);
                input.focus();
                return;
            }
            if (!input.value.trim()) {
                closeSearchUi();
                input.focus();
                setWrapperOpen(true);
                return;
            }
            runSearch();
        });

        input.addEventListener('focus', function () {
            setWrapperOpen(true);
        });

        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (!wrapper.contains(document.activeElement)) {
                    closeSearchUi();
                }
            }, 0);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runSearch();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeSearchUi();
                input.blur();
            }
        });

        input.addEventListener('input', function () {
            if (!wrapper.classList.contains('is-open')) {
                setWrapperOpen(true);
            }
            if (!input.value.trim()) {
                closeSearchUi();
                input.focus();
                return;
            }
            runSearch();
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                closeSearchUi();
            }
        });

        document.addEventListener('page-search:refresh', function () {
            if (input.value.trim()) runSearch();
        });

        setWrapperOpen(false);
    }

    function boot() {
        document.querySelectorAll('[data-page-search-wrapper]').forEach(initWrapper);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
