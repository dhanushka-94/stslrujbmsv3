{{-- Production requires `npm run build` (creates public/build/manifest.json). Fallback avoids 500 if build was not deployed. --}}
@if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    @php
        logger()->error('Vite manifest missing. Run: npm ci && npm run build — then deploy public/build/');
    @endphp
    <style>
        :root {
            --color-studio-primary: #1e3a5f;
            --color-studio-accent: #38bdf8;
            --color-studio-bg: #f1f5f9;
            --color-studio-bg-card: #ffffff;
            --color-studio-border: #e2e8f0;
            --color-studio-dark-card: #1e293b;
            --color-studio-dark-border: #334155;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #1e293b;
            background: var(--color-studio-bg);
        }
        .min-h-screen { min-height: 100vh; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .p-4 { padding: 1rem; }
        .w-full { width: 100%; }
        .max-w-md { max-width: 28rem; }
        .rounded-xl { border-radius: 0.75rem; }
        .shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }
        .border { border: 1px solid var(--color-studio-border); }
        .p-8 { padding: 2rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .text-center { text-align: center; }
        .text-2xl { font-size: 1.5rem; line-height: 2rem; }
        .font-semibold { font-weight: 600; }
        .text-sm { font-size: 0.875rem; }
        .text-slate-500 { color: #64748b; }
        .block { display: block; }
        .mb-1 { margin-bottom: 0.25rem; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--color-studio-border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        button[type="submit"], .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.625rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background: var(--color-studio-primary);
            color: #fff;
            font-weight: 500;
            cursor: pointer;
        }
        .text-red-600 { color: #dc2626; }
        .bg-amber-50 { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.8125rem; }
    </style>
    @if (config('app.debug'))
        <div class="bg-amber-50" style="max-width:28rem;margin:1rem auto;">
            Assets not built: run <code>npm run build</code> on the server and ensure <code>public/build/</code> is deployed.
        </div>
    @endif
@endif
