{{-- Full-page loading overlay (inline critical styles so it shows before Vite CSS). --}}
<style>
    #app-loading-screen {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        background:
            radial-gradient(1200px 600px at 50% -10%, rgba(59, 130, 246, 0.18), transparent 55%),
            linear-gradient(180deg, #f8fbff 0%, #e8f1ff 100%);
        color: #1e40af;
        transition: opacity 0.28s ease, visibility 0.28s ease;
    }
    #app-loading-screen.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    #app-loading-screen .app-loading-logo {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 10px 30px rgba(30, 64, 175, 0.12);
        border: 1px solid rgba(191, 219, 254, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    #app-loading-screen .app-loading-logo img {
        width: 3.25rem;
        height: auto;
        object-fit: contain;
    }
    #app-loading-screen .app-loading-title {
        margin: 0;
        font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        text-align: center;
        max-width: 16rem;
        line-height: 1.35;
        color: #1e3a8a;
    }
    #app-loading-screen .app-loading-sub {
        margin: 0;
        font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
    }
    #app-loading-screen .app-loading-spinner {
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 9999px;
        border: 2px solid rgba(59, 130, 246, 0.25);
        border-top-color: #1e40af;
        animation: app-loading-spin 0.75s linear infinite;
    }
    @keyframes app-loading-spin {
        to { transform: rotate(360deg); }
    }
    @media (prefers-color-scheme: dark) {
        #app-loading-screen {
            background:
                radial-gradient(1000px 500px at 50% -10%, rgba(59, 130, 246, 0.22), transparent 55%),
                linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #93c5fd;
        }
        #app-loading-screen .app-loading-logo {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35);
        }
        #app-loading-screen .app-loading-title { color: #e2e8f0; }
        #app-loading-screen .app-loading-sub { color: #94a3b8; }
        #app-loading-screen .app-loading-spinner {
            border-color: rgba(147, 197, 253, 0.2);
            border-top-color: #60a5fa;
        }
    }
</style>
<div id="app-loading-screen" role="status" aria-live="polite" aria-busy="true" aria-label="Loading">
    <div class="app-loading-logo">
        <img src="{{ asset('studio_salaru_logo.jpg') }}" alt="" width="52" height="52">
    </div>
    <p class="app-loading-title">{{ config('app.name') }}</p>
    <div class="app-loading-spinner" aria-hidden="true"></div>
    <p class="app-loading-sub">Loading</p>
</div>
<script>
(function () {
    var minMs = 280;
    var shownAt = Date.now();
    var hideTimer = null;

    function screenEl() {
        return document.getElementById('app-loading-screen');
    }

    function show() {
        var screen = screenEl();
        if (!screen) return;
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        shownAt = Date.now();
        screen.classList.remove('is-hidden');
        screen.setAttribute('aria-busy', 'true');
    }

    function hide() {
        var screen = screenEl();
        if (!screen || screen.classList.contains('is-hidden')) return;
        var wait = Math.max(0, minMs - (Date.now() - shownAt));
        hideTimer = setTimeout(function () {
            var el = screenEl();
            if (!el) return;
            el.classList.add('is-hidden');
            el.setAttribute('aria-busy', 'false');
        }, wait);
    }

    if (document.readyState === 'complete') {
        hide();
    } else {
        window.addEventListener('load', hide);
        setTimeout(hide, 12000);
    }

    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
        if (a.hasAttribute('download')) return;
        if (a.target && a.target !== '' && a.target !== '_self') return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;
        try {
            var url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
            if (url.href.split('#')[0] === window.location.href.split('#')[0] && url.hash) return;
        } catch (err) {
            return;
        }
        show();
    }, true);

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || e.defaultPrevented) return;
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'dialog') return;
        show();
    }, true);

    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) hide();
    });
})();
</script>
