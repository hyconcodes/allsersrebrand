<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }} - Find Trusted Artisans & Service Providers Near You</title>
    @snowfall
    @vite(['resources/js/app.js'])
    <meta name="description"
        content="Connect with verified artisans and service providers. Chat directly, view their work, and hire with confidence. Find local services on the map." />
    <meta name="author" content="Allsers" />
    <link rel="canonical" href="https://allsers.com" />
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#6a11cb" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="Allsers" />

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <meta property="og:title" content="Allsers - Find Trusted Artisans & Service Providers" />
    <meta property="og:description"
        content="Connect with verified artisans and service providers. Chat directly, view their work, and hire with confidence." />
    <meta property="og:type" content="website" />

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: '#6a11cb',
                        'primary-light': '#f7f1fe',
                        'primary-dark': '#5a0eb0',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-up': 'fadeUp 0.6s ease-out forwards',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-10px)'
                            },
                        },
                        fadeUp: {
                            from: {
                                opacity: '0',
                                transform: 'translateY(20px)'
                            },
                            to: {
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        },
                    },
                },
            },
        }
    </script>
    <style>
        .gradient-text {
            background: linear-gradient(135deg, #6a11cb 0%, #9b4dca 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-button {
            box-shadow: 0 4px 16px -2px rgba(106, 17, 203, 0.3);
        }

        .shadow-card {
            box-shadow: 0 4px 24px -4px rgba(106, 17, 203, 0.08);
        }

        .shadow-card-hover {
            box-shadow: 0 12px 40px -8px rgba(106, 17, 203, 0.15);
        }

        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .dark-glass {
            background: rgba(24, 24, 27, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="font-sans antialiased bg-zinc-50 text-black">
    <!-- Navbar -->
    <x-navbar />

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center bg-white overflow-hidden pt-20">
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div
                class="absolute top-20 right-[10%] w-[500px] h-[500px] bg-primary/10 rounded-full blur-[120px] animate-float">
            </div>
            <div class="absolute -bottom-20 -left-10 w-[600px] h-[600px] bg-purple-500/5 rounded-full blur-[120px] animate-float"
                style="animation-delay: 2s"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10 w-full">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-left animate-fade-up">
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary/5 rounded-full border border-primary/10 mb-8">
                        <span class="relative flex h-2 w-2">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                        </span>
                        <span class="text-sm font-bold text-primary">Trusted by 10,000+ users</span>
                    </div>
                    <h1
                        class="text-5xl sm:text-6xl lg:text-7xl font-black text-zinc-900 leading-[1.1] mb-6 tracking-tight">
                        Find Trusted <br><span class="gradient-text">Artisans</span> Near You
                    </h1>
                    <p class="text-xl text-zinc-600 max-w-xl mb-10 leading-relaxed font-medium">
                        Connect with verified service providers, chat directly, view their work, and hire with
                        confidence. All in one platform.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center gap-2 px-8 py-4 text-lg font-bold text-white bg-primary hover:bg-primary-dark rounded-2xl shadow-button hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                            Find a Service
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                            </svg>
                        </a>
                        <a href="{{ route('register') }}"
                            class="inline-flex items-center justify-center gap-2 px-8 py-4 text-lg font-bold text-zinc-900 bg-white border-2 border-zinc-200 hover:border-primary/30 hover:bg-zinc-50 rounded-2xl transition-all duration-300">
                            Join as Provider
                        </a>
                    </div>

                    <div class="grid grid-cols-3 gap-6 mt-16 lg:mt-24 border-t border-zinc-100 pt-8">
                        <div>
                            <div class="text-3xl font-black text-zinc-900">50K+</div>
                            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mt-1">Providers</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-zinc-900">100K+</div>
                            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mt-1">Jobs</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black text-zinc-900">4.9</div>
                            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mt-1">Rating</div>
                        </div>
                    </div>
                </div>

                <div class="relative lg:block hidden animate-fade-up" style="animation-delay: 0.2s">
                    <div class="relative rounded-[40px] overflow-hidden shadow-2xl border-8 border-white group">
                        <img src="{{ asset('assets/hero_artisan_work_1768047879333.png') }}" alt="Professional Artisan"
                            class="w-full h-auto object-cover group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent">
                        </div>
                        <div class="absolute bottom-8 left-8 right-8 p-6 glass rounded-3xl">
                            <div class="flex items-center gap-4">
                                <div
                                    class="size-12 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl shadow-lg">
                                    L</div>
                                <div>
                                    <div class="font-bold text-zinc-900">Verified Professional</div>
                                    <div class="text-sm text-zinc-600">Premium Woodworking Services</div>
                                </div>
                                <div class="ms-auto">
                                    <div class="flex text-yellow-500 gap-0.5">
                                        @for ($i = 0; $i < 5; $i++)
                                            <svg class="size-4 fill-current" viewBox="0 0 20 20">
                                                <path
                                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Badge -->
                    <div class="absolute -top-6 -right-6 p-4 glass rounded-2xl shadow-xl animate-float"
                        style="animation-delay: 1s">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-green-500 flex items-center justify-center text-white">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div class="font-bold text-sm text-zinc-900">Task Completed!</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-4">How It Works</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Get the help you need in three simple steps</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Step 1 -->
                <div class="relative group">
                    <div
                        class="hidden md:block absolute top-12 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-primary/30 to-primary/10">
                    </div>
                    <div
                        class="bg-primary-light rounded-3xl p-8 text-center transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 relative z-10">
                        <div
                            class="absolute -top-3 -right-3 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold">
                            1</div>
                        <div
                            class="w-20 h-20 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Search</h3>
                        <p class="text-gray-600 leading-relaxed">Browse services or use our map to find providers near
                            you.</p>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="relative group">
                    <div
                        class="hidden md:block absolute top-12 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-primary/30 to-primary/10">
                    </div>
                    <div
                        class="bg-primary-light rounded-3xl p-8 text-center transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 relative z-10">
                        <div
                            class="absolute -top-3 -right-3 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold">
                            2</div>
                        <div
                            class="w-20 h-20 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Connect</h3>
                        <p class="text-gray-600 leading-relaxed">Chat directly with providers, view their work, and
                            discuss your needs.</p>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="relative group">
                    <div
                        class="bg-primary-light rounded-3xl p-8 text-center transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 relative z-10">
                        <div
                            class="absolute -top-3 -right-3 w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-bold">
                            3</div>
                        <div
                            class="w-20 h-20 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-3">Hire</h3>
                        <p class="text-gray-600 leading-relaxed">Book the right person for the job with confidence and
                            transparency.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="px-4 sm:px-6 lg:px-8 py-24 bg-white relative">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16 animate-fade-up">
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 bg-primary/5 rounded-full text-primary text-xs font-bold uppercase tracking-wider mb-6">
                    What we offer
                </div>
                <h2 class="text-4xl sm:text-5xl font-black text-zinc-900 mb-6 leading-tight">Explore <span
                        class="gradient-text">Services</span></h2>
                <p class="text-xl text-zinc-600 max-w-2xl mx-auto font-medium">Find experts across all categories,
                    ready to help you with your next project.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8 max-w-6xl mx-auto">
                @php
                    $categories = [
                        [
                            'name' => 'Plumbing',
                            'count' => '2,400+',
                            'icon' =>
                                'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                        ],
                        [
                            'name' => 'Painting',
                            'count' => '1,800+',
                            'icon' =>
                                'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
                        ],
                        ['name' => 'Electrical', 'count' => '2,100+', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                        [
                            'name' => 'Cleaning',
                            'count' => '3,200+',
                            'icon' =>
                                'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                        ],
                        [
                            'name' => 'Auto Services',
                            'count' => '1,500+',
                            'icon' =>
                                'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
                        ],
                        [
                            'name' => 'Beauty',
                            'count' => '2,800+',
                            'icon' =>
                                'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z',
                        ],
                        [
                            'name' => 'Catering',
                            'count' => '900+',
                            'icon' =>
                                'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                        ],
                        [
                            'name' => 'Photography',
                            'count' => '1,200+',
                            'icon' =>
                                'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z',
                        ],
                    ];
                @endphp

                @foreach ($categories as $cat)
                    <div
                        class="group p-8 rounded-[32px] bg-zinc-50 border border-zinc-100 hover:bg-white hover:border-primary/20 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-2 cursor-pointer text-center">
                        <div
                            class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-sm group-hover:bg-primary group-hover:scale-110 transition-all duration-500">
                            <svg class="w-8 h-8 text-primary group-hover:text-white transition-colors duration-300"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="{{ $cat['icon'] }}"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-zinc-900 mb-1">{{ $cat['name'] }}</h3>
                        <p class="text-zinc-500 text-sm font-medium">{{ $cat['count'] }} Pros</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Dynamic Pros Section (Instant Value) -->
    <section class="relative px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-white overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent">
        </div>
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div class="max-w-2xl">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-4">
                        Work with Best <span class="gradient-text">Pros</span>
                    </h2>
                    <p class="text-lg text-gray-600">Verified professionals in your area, available to start right now.
                    </p>
                </div>
                <a href="{{ route('register') }}"
                    class="inline-flex items-center gap-2 text-primary font-bold hover:underline">
                    View all categories
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                        </path>
                    </svg>
                </a>
            </div>

            <div class="max-w-3xl mx-auto">
                <livewire:dashboard.pros-widget />
            </div>
        </div>
    </section>

    <!-- Why Choose Section -->
    <section class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-4">Why Choose Allsers?</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Built for trust, speed, and convenience</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Verified -->
                <div
                    class="p-8 rounded-[32px] bg-white border border-zinc-100 shadow-card hover:shadow-card-hover transition-all duration-300">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-zinc-900 mb-2">Verified Providers</h3>
                    <p class="text-zinc-500 leading-relaxed">Every artisan is vetted for quality and reliability,
                        ensuring you get the best service possible.</p>
                </div>
                <!-- Fast -->
                <div
                    class="p-8 rounded-[32px] bg-white border border-zinc-100 shadow-card hover:shadow-card-hover transition-all duration-300">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-zinc-900 mb-2">Fast Connections</h3>
                    <p class="text-zinc-500 leading-relaxed">Get responses within minutes. No more waiting days for a
                        quote or callback.</p>
                </div>
                <!-- Chat -->
                <div
                    class="p-8 rounded-[32px] bg-white border border-zinc-100 shadow-card hover:shadow-card-hover transition-all duration-300">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-zinc-900 mb-2">Direct Chat</h3>
                    <p class="text-zinc-500 leading-relaxed">Communicate directly with providers. Share photos, discuss
                        details, and hire with ease.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- For Artisans Section -->
    <section id="for-providers" class="px-4 sm:px-6 lg:px-8 py-24 bg-white overflow-hidden relative">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="animate-fade-up">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 bg-primary/5 rounded-full text-primary text-xs font-bold uppercase tracking-wider mb-6">
                        For Professionals
                    </div>
                    <h2 class="text-4xl sm:text-5xl font-black text-zinc-900 mb-6 leading-tight">
                        Grow Your <span class="gradient-text">Business</span><br>With Allsers
                    </h2>
                    <p class="text-lg text-zinc-600 mb-8 leading-relaxed font-medium">
                        Join thousands of artisans and businesses. Showcase your work, build your reputation, and
                        connect with customers ready to hire.
                    </p>
                    <div class="space-y-6 mb-10">
                        <div class="flex gap-4">
                            <div
                                class="size-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-lg">Massive Exposure</h4>
                                <p class="text-zinc-500">Get discovered by thousands of local clients looking for your
                                    specific skills.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div
                                class="size-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900 text-lg">Reputation Building</h4>
                                <p class="text-zinc-500">Collect verified reviews and build a portfolio that sells your
                                    services for you.</p>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-white bg-primary hover:bg-primary-dark rounded-2xl shadow-button hover:shadow-lg hover:-translate-y-1 transition-all duration-300 w-full sm:w-auto text-center">
                        Start Growing Today
                    </a>
                </div>

                <div class="relative animate-fade-up" style="animation-delay: 0.2s">
                    <img src="{{ asset('assets/pros_community_1768047934852.png') }}" alt="Pros Community"
                        class="rounded-[40px] shadow-2xl border-4 border-white">
                </div>
            </div>
        </div>
    </section>

    <!-- Map Discovery Section -->
    <section class="px-4 sm:px-6 lg:px-8 py-24 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="animate-fade-up">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 bg-primary/5 rounded-full text-primary text-xs font-bold uppercase tracking-wider mb-6">
                        Location Based
                    </div>
                    <h2 class="text-4xl sm:text-5xl font-black text-zinc-900 mb-6 leading-tight">
                        Discover Services <br><span class="gradient-text">On the Map</span>
                    </h2>
                    <p class="text-lg text-zinc-600 mb-8 leading-relaxed font-medium">
                        Our interactive map makes finding local service providers effortless. See who's nearby, check
                        their ratings, and connect instantly.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <div
                                class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-3">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-zinc-900 mb-1">Instant Proximity</h4>
                            <p class="text-sm text-zinc-500">Find experts exactly where you need them.</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                            <div
                                class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary mb-3">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-zinc-900 mb-1">Live Updates</h4>
                            <p class="text-sm text-zinc-500">Real-time availability of service providers.</p>
                        </div>
                    </div>
                </div>

                <div class="relative animate-fade-up" style="animation-delay: 0.2s">
                    <div class="absolute -inset-4 bg-primary/5 rounded-[40px] blur-2xl"></div>
                    <img src="{{ asset('assets/map_discovery_mockup_1768047916310.png') }}"
                        alt="Map Discovery Mockup"
                        class="relative rounded-[32px] shadow-2xl border-4 border-white w-full h-auto object-cover">
                </div>
            </div>
        </div>
    </section>

    <!-- Direct Chat Section -->
    <section class="px-4 sm:px-6 lg:px-8 py-24 bg-zinc-900 overflow-hidden relative">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-primary rounded-full blur-[120px]"></div>
            <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-purple-600 rounded-full blur-[120px]"></div>
        </div>

        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="lg:order-2 animate-fade-up">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-full text-white text-xs font-bold uppercase tracking-wider mb-6">
                        Seamless Communication
                    </div>
                    <h2 class="text-4xl sm:text-5xl font-black text-white mb-6 leading-tight">
                        Connect <span class="text-primary">Instantly</span><br>With Direct Chat
                    </h2>
                    <p class="text-lg text-zinc-400 mb-8 leading-relaxed">
                        Skip the middleman. Chat directly with your service provider to discuss details, share photos,
                        and get instant quotes. No more waiting for emails or phone calls.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4 text-white font-medium">
                            <div class="size-6 bg-primary rounded-full flex items-center justify-center">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            Private and Secure Messaging
                        </div>
                        <div class="flex items-center gap-4 text-white font-medium">
                            <div class="size-6 bg-primary rounded-full flex items-center justify-center">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            Share Images & Documents
                        </div>
                        <div class="flex items-center gap-4 text-white font-medium">
                            <div class="size-6 bg-primary rounded-full flex items-center justify-center">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            Real-time Notifications
                        </div>
                    </div>
                </div>

                <div class="lg:order-1 animate-fade-up" style="animation-delay: 0.2s">
                    <div class="max-w-md mx-auto">
                        <img src="{{ asset('assets/chat_interface_mockup_1768047896438.png') }}"
                            alt="Chat Interface Mockup" class="rounded-[40px] shadow-2xl">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="about" class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-4">Loved by Thousands</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">See what our community has to say</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6 max-w-6xl mx-auto">
                <!-- Testimonial 1 -->
                <div
                    class="bg-primary-light rounded-2xl p-8 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-2">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <p class="text-black mb-6 leading-relaxed">"Found an amazing electrician within minutes. The chat
                        feature made it so easy to discuss what I needed before booking."</p>
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 rounded-full flex items-center justify-center">
                            <img class="rounded-full border border-2 border-purple-600 w-12 h-12"
                                src="https://allsers.com/profilePics/66d96ccab2932-IMG-20240204-WA0013.jpg"
                                alt="">
                        </div>
                        <div>
                            <div class="font-semibold text-black">Olabintan Oluyeye</div>
                            <div class="text-sm text-gray-600">Beauty Home</div>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 2 -->
                <div
                    class="bg-primary-light rounded-2xl p-8 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-2">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <p class="text-black mb-6 leading-relaxed">"As a service provider, Allsers has helped me grow my
                        client base significantly. The direct chat and map features are game changers!"</p>
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 rounded-full flex items-center justify-center">
                            <img class="rounded-full border border-2 border-purple-600 w-12 h-12"
                                src="https://allsers.com/profilePics/66d82e9f84165-images%20(19)_20240903203759.jpeg"
                                alt="">
                        </div>
                        <div>
                            <div class="font-semibold text-black">Lara Blessing</div>
                            <div class="text-sm text-gray-600">Fashion Designer</div>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 3 -->
                <div
                    class="bg-primary-light rounded-2xl p-8 hover:shadow-card-hover transition-all duration-300 hover:-translate-y-2">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <svg class="w-5 h-5 text-primary fill-primary" viewBox="0 0 24 24">
                            <path
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <p class="text-black mb-6 leading-relaxed">"The map feature is incredibly useful. I can see all the
                        providers near my business and compare them easily."</p>
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 rounded-full flex items-center justify-center">
                            <img class="rounded-full border border-2 border-purple-600 w-12 h-12"
                                src="https://allsers.com/profilePics/66d77d642d89f-75d5fcc7f923c80f2ee604734117965b.png"
                                alt="">
                        </div>
                        <div>
                            <div class="font-semibold text-black">Adewale Damilola</div>
                            <div class="text-sm text-gray-600">Photographer</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-primary-light relative overflow-hidden">
        <div class="absolute inset-0">
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary/5 rounded-full blur-3xl">
            </div>
        </div>
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-6">Ready to Get Started?</h2>
                <p class="text-lg text-gray-600 mb-10 max-w-2xl mx-auto">
                    Join thousands of satisfied users finding trusted services every day. Your perfect match is just a
                    click away.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-white bg-primary hover:bg-primary-dark rounded-2xl shadow-button hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                        Find a Service Now
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </a>
                    <a href="{{ route('register') }}"
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-zinc-900 bg-white border-2 border-zinc-200 hover:border-primary/30 hover:bg-zinc-50 rounded-2xl transition-all duration-300">
                        List Your Service
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <x-footer />

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 20) {
                navbar.classList.remove('bg-transparent');
                navbar.classList.add('bg-white/95', 'backdrop-blur-md', 'shadow-sm', 'border-b',
                    'border-gray-200');
            } else {
                navbar.classList.add('bg-transparent');
                navbar.classList.remove('bg-white/95', 'backdrop-blur-md', 'shadow-sm', 'border-b',
                    'border-gray-200');
            }
        });

        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                    document.getElementById('mobile-menu').classList.add('hidden');
                }
            });
        }); <
        x - pwa - scripts / >
            <
            /body>

            <
            /html>
