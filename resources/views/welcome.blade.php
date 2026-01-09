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
            box-shadow: 0 8px 32px -4px rgba(106, 17, 203, 0.15);
        }
    </style>
</head>

<body class="font-sans antialiased bg-white text-black">
    <!-- Navbar -->
    <x-navbar />

    <!-- Hero Section -->
    <section class="relative min-h-[90vh] flex items-center bg-primary-light overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 left-10 w-72 h-72 bg-primary/5 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-primary/10 rounded-full blur-3xl animate-float"
                style="animation-delay: 2s"></div>
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/5 rounded-full blur-3xl">
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-card mb-8">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z">
                        </path>
                    </svg>
                    <span class="text-sm font-medium text-black">Trusted by 10,000+ users</span>
                </div>
                <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-black leading-tight mb-6">
                    Find Trusted <span class="gradient-text">Artisans</span><br>Near You
                </h1>
                <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto mb-10">
                    Connect with verified service providers, chat directly, view their work, and hire with confidence.
                    All in one platform.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-white bg-primary hover:bg-primary-dark rounded-2xl shadow-button hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                        Find a Service
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </button>
                    <button
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-primary border-2 border-primary bg-transparent hover:bg-primary/5 rounded-2xl transition-all duration-300">
                        Join as Provider
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-8 mt-16 max-w-2xl mx-auto">
                    <div class="text-center">
                        <div class="text-2xl sm:text-3xl font-bold text-black">50K+</div>
                        <div class="text-sm text-gray-600 mt-1">Active Providers</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl sm:text-3xl font-bold text-black">100K+</div>
                        <div class="text-sm text-gray-600 mt-1">Jobs Completed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl sm:text-3xl font-bold text-black">4.9</div>
                        <div class="text-sm text-gray-600 mt-1">Average Rating</div>
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

    <!-- Service Categories Section -->
    <section id="services" class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-primary-light">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-4">Explore Services</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Find experts across all categories, ready to help
                </p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 max-w-5xl mx-auto">
                <!-- Plumbing -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Plumbing</h3>
                    <p class="text-sm text-gray-600">2,400+ providers</p>
                </div>
                <!-- Painting -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Painting</h3>
                    <p class="text-sm text-gray-600">1,800+ providers</p>
                </div>
                <!-- Electrical -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Electrical</h3>
                    <p class="text-sm text-gray-600">2,100+ providers</p>
                </div>
                <!-- Cleaning -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Cleaning</h3>
                    <p class="text-sm text-gray-600">3,200+ providers</p>
                </div>
                <!-- Auto Services -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Auto Services</h3>
                    <p class="text-sm text-gray-600">1,500+ providers</p>
                </div>
                <!-- Beauty -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Beauty</h3>
                    <p class="text-sm text-gray-600">2,800+ providers</p>
                </div>
                <!-- Catering -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                            </path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Catering</h3>
                    <p class="text-sm text-gray-600">900+ providers</p>
                </div>
                <!-- Photography -->
                <div
                    class="group bg-white rounded-2xl p-6 text-center cursor-pointer transition-all duration-300 hover:shadow-card-hover hover:-translate-y-2 border border-transparent hover:border-primary/20">
                    <div
                        class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                        <svg class="w-7 h-7 text-primary group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-black mb-1">Photography</h3>
                    <p class="text-sm text-gray-600">1,200+ providers</p>
                </div>
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
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
                <!-- Verified -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">Verified Providers</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Every artisan is vetted for quality and
                            reliability.</p>
                    </div>
                </div>
                <!-- Fast -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">Fast Connections</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Get responses within minutes, not days.</p>
                    </div>
                </div>
                <!-- Chat -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">Direct Chat</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Communicate directly with providers before
                            hiring.</p>
                    </div>
                </div>
                <!-- Map -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">Map Discovery</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Find services near you with our interactive
                            map.</p>
                    </div>
                </div>
                <!-- 24hr -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">24-Hour Ads</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Fresh, time-sensitive offers updated daily.
                        </p>
                    </div>
                </div>
                <!-- Reviews -->
                <div
                    class="flex items-start gap-4 p-6 rounded-2xl bg-primary-light/50 hover:bg-primary-light transition-colors duration-300">
                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-black mb-1">Transparent Reviews</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Read real feedback from verified customers.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- For Artisans Section -->
    <section id="for-providers"
        class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-primary relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-4">Are You a Service Provider?
                    </h2>
                    <p class="text-lg text-white/80 max-w-2xl mx-auto">Join thousands of artisans and businesses
                        growing with Allsers</p>
                </div>
                <div class="grid md:grid-cols-3 gap-6 mb-12">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-center border border-white/20">
                        <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white mb-2">Reach More Clients</h3>
                        <p class="text-white/70 text-sm">Get discovered by thousands of people looking for your
                            services.</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-center border border-white/20">
                        <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white mb-2">24-Hour Ads</h3>
                        <p class="text-white/70 text-sm">Post time-sensitive offers that create urgency and drive
                            engagement.</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 text-center border border-white/20">
                        <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white mb-2">Grow Your Business</h3>
                        <p class="text-white/70 text-sm">Build your reputation with reviews and showcase your best
                            work.</p>
                    </div>
                </div>
                <div class="text-center">
                    <button
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-primary bg-white hover:bg-gray-100 rounded-2xl transition-all duration-300">
                        Start Growing Today
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Discovery Section -->
    <section class="px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 bg-primary-light">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="order-2 lg:order-1">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-black mb-6">
                        Discover Services <span class="gradient-text">On the Map</span>
                    </h2>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        Our interactive map makes finding local service providers effortless. See who's nearby, check
                        their ratings, and connect instantly. No more endless searching—just tap and hire.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                </svg>
                            </div>
                            <span class="text-black">See providers in your area instantly</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                </svg>
                            </div>
                            <span class="text-black">Filter by service type and rating</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                </svg>
                            </div>
                            <span class="text-black">View availability in real-time</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                    </path>
                                </svg>
                            </div>
                            <span class="text-black">Get directions to their location</span>
                        </div>
                    </div>
                </div>
                <!-- Map Mockup -->
                <div class="order-1 lg:order-2">
                    <div
                        class="relative bg-white rounded-3xl shadow-card-hover overflow-hidden aspect-square max-w-lg mx-auto">
                        <div class="absolute inset-0 bg-gradient-to-br from-primary-light to-gray-100">
                            <div class="absolute inset-0 opacity-30"
                                style="background-image: linear-gradient(#e5e7eb 1px, transparent 1px), linear-gradient(90deg, #e5e7eb 1px, transparent 1px); background-size: 40px 40px;">
                            </div>
                        </div>
                        <div class="absolute inset-0">
                            <div class="absolute top-1/4 left-0 right-0 h-2 bg-white/80"></div>
                            <div class="absolute top-2/3 left-0 right-0 h-3 bg-white/80"></div>
                            <div class="absolute left-1/3 top-0 bottom-0 w-2 bg-white/80"></div>
                            <div class="absolute left-2/3 top-0 bottom-0 w-3 bg-white/80"></div>
                        </div>
                        <!-- Pins -->
                        <div class="absolute animate-float"
                            style="top: 20%; left: 25%; animation-delay: 0s; animation-duration: 4s;">
                            <div class="relative">
                                <div class="absolute -inset-2 bg-primary/20 rounded-full animate-pulse"></div>
                                <div
                                    class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shadow-button relative">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="absolute animate-float"
                            style="top: 35%; left: 60%; animation-delay: 0.2s; animation-duration: 4s;">
                            <div class="relative">
                                <div class="absolute -inset-2 bg-primary/20 rounded-full animate-pulse"></div>
                                <div
                                    class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shadow-button relative">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="absolute animate-float"
                            style="top: 55%; left: 40%; animation-delay: 0.4s; animation-duration: 4s;">
                            <div class="relative">
                                <div class="absolute -inset-2 bg-primary/20 rounded-full animate-pulse"></div>
                                <div
                                    class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shadow-button relative">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="absolute animate-float"
                            style="top: 70%; left: 70%; animation-delay: 0.6s; animation-duration: 4s;">
                            <div class="relative">
                                <div class="absolute -inset-2 bg-primary/20 rounded-full animate-pulse"></div>
                                <div
                                    class="w-10 h-10 bg-primary rounded-full flex items-center justify-center shadow-button relative">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <!-- Center user -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                            <div class="relative">
                                <div class="absolute -inset-4 bg-primary/10 rounded-full animate-pulse"></div>
                                <div class="absolute -inset-8 bg-primary/5 rounded-full animate-pulse"
                                    style="animation-delay: 0.5s;"></div>
                                <div class="w-6 h-6 bg-black rounded-full border-4 border-white shadow-lg"></div>
                            </div>
                        </div>
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
                        client base significantly. The 24 hour ads feature is genius!"</p>
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
                    <button
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-white bg-primary hover:bg-primary-dark rounded-2xl shadow-button hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                        Find a Service Now
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </button>
                    <button
                        class="inline-flex items-center justify-center gap-2 px-10 py-4 text-lg font-bold text-primary border-2 border-primary bg-transparent hover:bg-primary/5 rounded-2xl transition-all duration-300">
                        List Your Service
                    </button>
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
                navbar.classList.add('bg-white/95', 'backdrop-blur-md', 'shadow-sm', 'border-b', 'border-gray-200');
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
        });
    </script>
</body>

</html>
