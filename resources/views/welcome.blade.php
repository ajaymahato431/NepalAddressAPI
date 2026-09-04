<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nepal Address API — High-Performance Nepal Address Data</title>
    <meta name="description" content="A blazingly fast, reliable REST API for Nepal's administrative divisions: 7 provinces, 77 districts, and 753 local level municipalities.">
    <link rel="icon" type="image/png" href="https://notedinsights.com/wp-content/uploads/2024/01/android-chrome-512x512-1-75x75.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        pre::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        pre::-webkit-scrollbar-thumb {
            background-color: #334155;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 font-sans antialiased selection:bg-brand-500 selection:text-white min-h-screen flex flex-col">

    <!-- Navbar -->
    <header class="sticky top-0 z-50 backdrop-blur-md bg-slate-950/80 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-500 to-emerald-400 text-white font-bold text-lg shadow-lg shadow-indigo-500/20">
                    🇳🇵
                </span>
                <div>
                    <span class="text-lg font-bold tracking-tight text-white">NepalAddress<span class="text-indigo-400">API</span></span>
                    <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">v1.2</span>
                </div>
            </div>
            <nav class="hidden md:flex items-center space-x-6 text-sm font-medium text-slate-300">
                <a href="#endpoints" class="hover:text-white transition">Endpoints</a>
                <a href="#demo" class="hover:text-white transition">Cascade Demo</a>
                <a href="#playground" class="hover:text-white transition">Interactive Console</a>
                <a href="#search" class="hover:text-white transition">Live Search</a>
                <a href="#examples" class="hover:text-white transition">SDK Examples</a>
            </nav>
            <div class="flex items-center space-x-3">
                <a href="https://github.com/ajaymahato431/NepalAddressAPI" target="_blank" rel="noopener noreferrer" class="inline-flex items-center space-x-2 text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                    <span>GitHub</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative overflow-hidden py-16 sm:py-24 border-b border-slate-900">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.indigo.900/25),theme(colors.slate.950))]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- Badges -->
            <div class="flex flex-wrap items-center justify-center gap-2 mb-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Verified 753 Local Levels
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    7 Provinces & 77 Districts
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                    Case-Insensitive & Slug Support
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20">
                    Dual Koshi / Pradesh-1 Support
                </span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight">
                The Standard Address API for <span class="bg-gradient-to-r from-indigo-400 via-sky-300 to-emerald-400 bg-clip-text text-transparent">Nepal</span>
            </h1>
            <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Clean, reliable, and standardized address data for developers building forms, e-commerce checkout, logistics, and verification systems in Nepal.
            </p>

            <!-- Quick Base URL Box -->
            <div class="mt-8 max-w-xl mx-auto">
                <div class="flex items-center justify-between rounded-xl bg-slate-900/90 border border-slate-800 p-2 shadow-2xl backdrop-blur-sm">
                    <div class="flex items-center space-x-3 px-3 overflow-x-auto text-left">
                        <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400">BASE URL</span>
                        <code id="baseUrlText" class="text-sm font-mono text-slate-200">http://localhost:8000/api</code>
                    </div>
                    <button onclick="copyBaseUrl()" class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg transition flex items-center space-x-1.5 shrink-0 shadow">
                        <span id="copyIcon">📋</span>
                        <span id="copyLabel">Copy</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive Cascade Demo Section -->
    <section id="demo" class="py-16 border-b border-slate-900 bg-slate-900/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Live Component</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Cascading Address Selector</p>
                <p class="mt-3 text-slate-400 text-sm">Experience how seamlessly this API powers dynamic Province &rarr; District &rarr; Municipality dropdown cascades.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Dropdown Form -->
                <div class="lg:col-span-6 bg-slate-900 rounded-2xl border border-slate-800 p-6 sm:p-8 shadow-xl">
                    <h3 class="text-base font-semibold text-white mb-6 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Interactive Address Form
                    </h3>
                    
                    <div class="space-y-5">
                        <!-- Province Dropdown -->
                        <div>
                            <label class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">1. Select Province</label>
                            <select id="cascadeProvince" onchange="onProvinceChange()" class="w-full bg-slate-950 border border-slate-700 text-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                                <option value="">Loading provinces...</option>
                            </select>
                        </div>

                        <!-- District Dropdown -->
                        <div>
                            <label class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">2. Select District</label>
                            <select id="cascadeDistrict" onchange="onDistrictChange()" disabled class="w-full bg-slate-950 border border-slate-700 text-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <option value="">Choose a province first</option>
                            </select>
                        </div>

                        <!-- Municipality Dropdown -->
                        <div>
                            <label class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">3. Select Municipality / Local Level</label>
                            <select id="cascadeMunicipal" onchange="onMunicipalChange()" disabled class="w-full bg-slate-950 border border-slate-700 text-slate-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <option value="">Choose a district first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Form Payload Result -->
                <div class="lg:col-span-6 bg-slate-900 rounded-2xl border border-slate-800 p-6 sm:p-8 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-semibold text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            Selected Address Payload
                        </h3>
                        <span id="cascadeStatus" class="text-xs font-mono px-2.5 py-1 rounded bg-slate-800 text-slate-400">Ready</span>
                    </div>
                    <pre class="bg-slate-950 rounded-xl border border-slate-800/80 p-4 text-xs font-mono text-emerald-400 overflow-x-auto min-h-[190px]"><code id="cascadeOutput">{
  "province": null,
  "district": null,
  "municipality": null,
  "country": "Nepal"
}</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Search Section -->
    <section id="search" class="py-16 border-b border-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Global Autocomplete</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Instant Search Endpoint</p>
                <p class="mt-3 text-slate-400 text-sm">Query any municipality, district, or province with real-time matching via <code>/api/search?q={term}</code>.</p>
            </div>

            <div class="max-w-3xl mx-auto">
                <div class="relative">
                    <input type="text" id="liveSearchInput" oninput="debounceSearch()" placeholder="Try typing 'Bharatpur', 'Chitwan', 'Koshi', 'Pokhara'..." class="w-full bg-slate-900 border border-slate-700 text-slate-100 rounded-2xl px-6 py-4 text-base focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition shadow-xl pl-13">
                    <span class="absolute left-4 top-4.5 text-slate-400 text-lg">🔍</span>
                </div>

                <!-- Search Results Container -->
                <div id="searchResults" class="mt-4 space-y-2 hidden"></div>
            </div>
        </div>
    </section>

    <!-- Interactive API Playground -->
    <section id="playground" class="py-16 border-b border-slate-900 bg-slate-900/30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-xs font-bold uppercase tracking-wider text-sky-400">Test In Real-Time</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">API Testing Console</p>
                <p class="mt-3 text-slate-400 text-sm">Choose an endpoint or type custom parameters to test immediate JSON responses.</p>
            </div>

            <div class="bg-slate-900 rounded-2xl border border-slate-800 p-6 shadow-2xl">
                <!-- Preset buttons -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <button onclick="setPlaygroundUrl('/api/provinces')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/provinces</button>
                    <button onclick="setPlaygroundUrl('/api/districts')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/districts</button>
                    <button onclick="setPlaygroundUrl('/api/districts/bagmati')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/districts/bagmati</button>
                    <button onclick="setPlaygroundUrl('/api/districts/koshi')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/districts/koshi</button>
                    <button onclick="setPlaygroundUrl('/api/municipals/kathmandu')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/municipals/kathmandu</button>
                    <button onclick="setPlaygroundUrl('/api/municipals/chitwan?case=title')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/municipals/chitwan?case=title</button>
                    <button onclick="setPlaygroundUrl('/api/stats')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition">/api/stats</button>
                </div>

                <!-- Input & Send -->
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <div class="flex items-center w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2">
                        <span class="text-xs font-bold text-emerald-400 mr-2">GET</span>
                        <input type="text" id="playgroundInput" value="/api/provinces" class="bg-transparent border-none w-full text-slate-100 font-mono text-sm focus:outline-none">
                    </div>
                    <button onclick="runPlayground()" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-medium text-sm text-white shadow-lg transition flex items-center justify-center space-x-2 shrink-0">
                        <span>Send Request</span>
                    </button>
                </div>

                <!-- Response Viewer -->
                <div class="mt-6">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800 text-xs">
                        <div class="flex items-center space-x-3">
                            <span id="pgStatus" class="font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-400">Status: -</span>
                            <span id="pgTime" class="text-slate-400">Time: -</span>
                        </div>
                        <button onclick="copyPlaygroundOutput()" class="text-slate-400 hover:text-white transition">Copy JSON</button>
                    </div>
                    <pre class="bg-slate-950 rounded-xl border border-slate-800 p-4 mt-3 text-xs font-mono text-slate-200 overflow-x-auto max-h-96"><code id="pgOutput">// Response will appear here...</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Endpoints Documentation Grid -->
    <section id="endpoints" class="py-16 border-b border-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Complete Reference</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Available Endpoints</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Endpoint 1 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/provinces</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Retrieve all 7 official provinces of Nepal.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Params: <code>?case=title|lower</code>
                    </div>
                </div>

                <!-- Endpoint 2 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/districts</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Retrieve all 77 official districts of Nepal.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Params: <code>?case=title|lower</code>
                    </div>
                </div>

                <!-- Endpoint 3 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/districts/{provinceName}</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Retrieve districts for a province. Supports aliases (e.g. <code>koshi</code> & <code>pradesh-1</code>) and case-insensitivity.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Example: <code>/api/districts/bagmati</code>
                    </div>
                </div>

                <!-- Endpoint 4 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/municipals/{districtName}</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Retrieve municipalities for any district. Handles spaces, hyphens, and aliases.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Example: <code>/api/municipals/chitwan?case=title</code>
                    </div>
                </div>

                <!-- Endpoint 5 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/search?q={query}</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Fuzzy search across all provinces, districts, and municipalities with parent context.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Params: <code>q=term</code>, <code>limit=20</code>, <code>case=title|lower</code>
                    </div>
                </div>

                <!-- Endpoint 6 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/all | /api/hierarchy</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Complete nested tree (Provinces &rarr; Districts &rarr; Municipalities) for client caching.</p>
                    <div class="mt-3 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg">
                        Params: <code>?case=title|lower</code>
                    </div>
                </div>

                <!-- Endpoint 7 -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 hover:border-slate-700 transition md:col-span-2">
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">GET</span>
                        <code class="text-sm font-semibold text-white">/api/stats</code>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Overview metrics: total provinces (7), total districts (77), total municipalities (753), and breakdowns per province.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SDK / Code Examples -->
    <section id="examples" class="py-16 border-b border-slate-900 bg-slate-900/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-xs font-bold uppercase tracking-wider text-purple-400">Integration Snippets</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Client Examples</p>
            </div>

            <!-- Tabs -->
            <div class="max-w-4xl mx-auto bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden shadow-2xl">
                <div class="flex border-b border-slate-800 bg-slate-950/60 px-4 pt-3 space-x-2">
                    <button onclick="showCodeTab('curl')" id="tab-btn-curl" class="px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-indigo-500 text-white bg-slate-900">cURL</button>
                    <button onclick="showCodeTab('js')" id="tab-btn-js" class="px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-white">JavaScript (Fetch)</button>
                    <button onclick="showCodeTab('php')" id="tab-btn-php" class="px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-white">PHP (Laravel Http)</button>
                    <button onclick="showCodeTab('python')" id="tab-btn-python" class="px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-white">Python (Requests)</button>
                </div>

                <div class="p-6">
                    <!-- cURL Code -->
                    <pre id="code-curl" class="text-xs font-mono text-slate-200 overflow-x-auto"><code># 1. Fetch provinces
curl -X GET "http://localhost:8000/api/provinces"

# 2. Fetch districts of Bagmati
curl -X GET "http://localhost:8000/api/districts/bagmati"

# 3. Fetch municipalities in Title Case
curl -X GET "http://localhost:8000/api/municipals/chitwan?case=title"

# 4. Search
curl -X GET "http://localhost:8000/api/search?q=bharatpur"</code></pre>

                    <!-- JS Code -->
                    <pre id="code-js" class="text-xs font-mono text-slate-200 overflow-x-auto hidden"><code>// Fetch provinces
const provincesRes = await fetch('http://localhost:8000/api/provinces');
const { provinces } = await provincesRes.json();

// Fetch districts for selected province
const districtsRes = await fetch(`http://localhost:8000/api/districts/${selectedProvince}`);
const { districts } = await districtsRes.json();

// Fetch municipalities
const municipalsRes = await fetch(`http://localhost:8000/api/municipals/${selectedDistrict}?case=title`);
const { municipals } = await municipalsRes.json();</code></pre>

                    <!-- PHP Code -->
                    <pre id="code-php" class="text-xs font-mono text-slate-200 overflow-x-auto hidden"><code>use Illuminate\Support\Facades\Http;

// Fetch provinces
$provinces = Http::get('http://localhost:8000/api/provinces')->json('provinces');

// Fetch districts
$districts = Http::get('http://localhost:8000/api/districts/bagmati')->json('districts');

// Fetch municipalities in Title Case
$municipals = Http::get('http://localhost:8000/api/municipals/chitwan', [
    'case' => 'title'
])->json('municipals');</code></pre>

                    <!-- Python Code -->
                    <pre id="code-python" class="text-xs font-mono text-slate-200 overflow-x-auto hidden"><code>import requests

BASE_URL = "http://localhost:8000/api"

# Get all provinces
provinces = requests.get(f"{BASE_URL}/provinces").json()['provinces']

# Get districts
districts = requests.get(f"{BASE_URL}/districts/bagmati").json()['districts']

# Get municipalities
municipals = requests.get(f"{BASE_URL}/municipals/chitwan", params={"case": "title"}).json()['municipals']</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto border-t border-slate-900 bg-slate-950 py-10 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>&copy; {{ date('Y') }} NepalAddressAPI. Open source under the <a href="https://opensource.org/licenses/MIT" class="text-slate-400 underline hover:text-white">MIT License</a>.</p>
            <p class="mt-2">Crafted with ❤️ for the Nepal developer community.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        const baseUrl = window.location.origin + '/api';
        document.getElementById('baseUrlText').innerText = baseUrl;

        function copyBaseUrl() {
            navigator.clipboard.writeText(baseUrl);
            document.getElementById('copyLabel').innerText = 'Copied!';
            setTimeout(() => document.getElementById('copyLabel').innerText = 'Copy', 2000);
        }

        // --- Cascading Dropdown Demo Logic ---
        let selectedProvince = '';
        let selectedDistrict = '';
        let selectedMunicipal = '';

        async function initCascade() {
            const selectProv = document.getElementById('cascadeProvince');
            try {
                const res = await fetch(`${baseUrl}/provinces?case=title`);
                const data = await res.json();
                selectProv.innerHTML = '<option value="">-- Choose Province --</option>';
                data.provinces.forEach(p => {
                    selectProv.innerHTML += `<option value="${p.toLowerCase()}">${p}</option>`;
                });
            } catch (err) {
                selectProv.innerHTML = '<option value="">Failed to load</option>';
            }
        }

        async function onProvinceChange() {
            const provSelect = document.getElementById('cascadeProvince');
            const distSelect = document.getElementById('cascadeDistrict');
            const munSelect = document.getElementById('cascadeMunicipal');

            selectedProvince = provSelect.value;
            selectedDistrict = '';
            selectedMunicipal = '';
            updateCascadeOutput();

            distSelect.innerHTML = '<option value="">Loading districts...</option>';
            distSelect.disabled = true;
            munSelect.innerHTML = '<option value="">Choose a district first</option>';
            munSelect.disabled = true;

            if (!selectedProvince) return;

            try {
                const res = await fetch(`${baseUrl}/districts/${selectedProvince}?case=title`);
                const data = await res.json();
                distSelect.innerHTML = '<option value="">-- Choose District --</option>';
                data.districts.forEach(d => {
                    distSelect.innerHTML += `<option value="${d.toLowerCase()}">${d}</option>`;
                });
                distSelect.disabled = false;
            } catch (e) {
                distSelect.innerHTML = '<option value="">Failed to load</option>';
            }
        }

        async function onDistrictChange() {
            const distSelect = document.getElementById('cascadeDistrict');
            const munSelect = document.getElementById('cascadeMunicipal');

            selectedDistrict = distSelect.value;
            selectedMunicipal = '';
            updateCascadeOutput();

            munSelect.innerHTML = '<option value="">Loading municipalities...</option>';
            munSelect.disabled = true;

            if (!selectedDistrict) return;

            try {
                const res = await fetch(`${baseUrl}/municipals/${selectedDistrict}?case=title`);
                const data = await res.json();
                munSelect.innerHTML = '<option value="">-- Choose Municipality --</option>';
                data.municipals.forEach(m => {
                    munSelect.innerHTML += `<option value="${m}">${m}</option>`;
                });
                munSelect.disabled = false;
            } catch (e) {
                munSelect.innerHTML = '<option value="">Failed to load</option>';
            }
        }

        function onMunicipalChange() {
            const munSelect = document.getElementById('cascadeMunicipal');
            selectedMunicipal = munSelect.value;
            updateCascadeOutput();
        }

        function updateCascadeOutput() {
            const out = {
                country: "Nepal",
                province: selectedProvince || null,
                district: selectedDistrict || null,
                municipality: selectedMunicipal || null,
            };
            document.getElementById('cascadeOutput').innerText = JSON.stringify(out, null, 2);
            document.getElementById('cascadeStatus').innerText = selectedMunicipal ? 'Address Complete' : 'In Progress';
            document.getElementById('cascadeStatus').className = selectedMunicipal 
                ? 'text-xs font-mono px-2.5 py-1 rounded bg-emerald-500/20 text-emerald-400' 
                : 'text-xs font-mono px-2.5 py-1 rounded bg-slate-800 text-slate-400';
        }

        // --- Live Search ---
        let searchTimer = null;
        function debounceSearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(performSearch, 300);
        }

        async function performSearch() {
            const input = document.getElementById('liveSearchInput').value.trim();
            const container = document.getElementById('searchResults');

            if (!input) {
                container.innerHTML = '';
                container.classList.add('hidden');
                return;
            }

            try {
                const res = await fetch(`${baseUrl}/search?q=${encodeURIComponent(input)}&case=title&limit=8`);
                const data = await res.json();

                if (!data.results || data.results.length === 0) {
                    container.innerHTML = '<div class="p-4 text-slate-400 text-sm bg-slate-900 rounded-xl border border-slate-800">No matching locations found.</div>';
                    container.classList.remove('hidden');
                    return;
                }

                container.innerHTML = data.results.map(r => `
                    <div class="flex items-center justify-between p-3.5 bg-slate-900 hover:bg-slate-800/80 rounded-xl border border-slate-800/80 transition">
                        <div>
                            <span class="font-semibold text-white text-sm">${r.name}</span>
                            <span class="text-xs text-slate-400 ml-2">
                                ${r.district ? `District: ${r.district} • ` : ''}Province: ${r.province}
                            </span>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded ${
                            r.type === 'province' ? 'bg-purple-500/20 text-purple-300' :
                            r.type === 'district' ? 'bg-sky-500/20 text-sky-300' :
                            'bg-emerald-500/20 text-emerald-300'
                        }">${r.type}</span>
                    </div>
                `).join('');
                container.classList.remove('hidden');
            } catch (err) {
                container.innerHTML = '<div class="p-4 text-red-400 text-sm">Search request failed.</div>';
                container.classList.remove('hidden');
            }
        }

        // --- Playground Console ---
        function setPlaygroundUrl(url) {
            document.getElementById('playgroundInput').value = url;
            runPlayground();
        }

        async function runPlayground() {
            const path = document.getElementById('playgroundInput').value.trim();
            const url = path.startsWith('http') ? path : window.location.origin + (path.startsWith('/') ? path : '/' + path);

            const out = document.getElementById('pgOutput');
            const statusBadge = document.getElementById('pgStatus');
            const timeBadge = document.getElementById('pgTime');

            out.innerText = 'Loading...';
            const startTime = performance.now();

            try {
                const res = await fetch(url);
                const duration = Math.round(performance.now() - startTime);
                const json = await res.json();

                statusBadge.innerText = `Status: ${res.status} ${res.statusText || 'OK'}`;
                statusBadge.className = res.ok ? 'font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400' : 'font-mono px-2 py-0.5 rounded bg-rose-500/20 text-rose-400';
                timeBadge.innerText = `Time: ${duration}ms`;

                out.innerText = JSON.stringify(json, null, 2);
            } catch (err) {
                statusBadge.innerText = 'Network Error';
                statusBadge.className = 'font-mono px-2 py-0.5 rounded bg-rose-500/20 text-rose-400';
                out.innerText = err.toString();
            }
        }

        function copyPlaygroundOutput() {
            const text = document.getElementById('pgOutput').innerText;
            navigator.clipboard.writeText(text);
            alert('JSON response copied to clipboard!');
        }

        // --- Code Tab Switcher ---
        function showCodeTab(tab) {
            ['curl', 'js', 'php', 'python'].forEach(t => {
                document.getElementById(`code-${t}`).classList.add('hidden');
                document.getElementById(`tab-btn-${t}`).className = 'px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-transparent text-slate-400 hover:text-white';
            });
            document.getElementById(`code-${tab}`).classList.remove('hidden');
            document.getElementById(`tab-btn-${tab}`).className = 'px-4 py-2 text-xs font-semibold rounded-t-lg border-b-2 border-indigo-500 text-white bg-slate-900';
        }

        // Init on page load
        document.addEventListener('DOMContentLoaded', () => {
            initCascade();
            runPlayground();
        });
    </script>
</body>

</html>
