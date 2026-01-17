<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-transparent">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between h-16 sm:h-20 px-4 sm:px-6 lg:px-8">
            <a href="/" class="flex items-center gap-2 text-xl sm:text-2xl font-bold">
                <img src="{{ asset('assets/allsers.png') }}" alt="{{ config('app.name') }}" class="h-8 w-8" />
                <span class="gradient-text">{{ config('app.name') }}</span>
            </a>
            <div class="hidden md:flex items-center gap-8">
                <a href="#how-it-works"
                    class="text-black/80 hover:text-primary font-medium transition-colors duration-200">How It
                    Works</a>
                <a href="#services"
                    class="text-black/80 hover:text-primary font-medium transition-colors duration-200">Services</a>
                <a href="#for-providers"
                    class="text-black/80 hover:text-primary font-medium transition-colors duration-200">For
                    Providers</a>
                <a href="#about"
                    class="text-black/80 hover:text-primary font-medium transition-colors duration-200">About</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-button hover:shadow-lg transition-all duration-200">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="px-4 py-2 text-sm font-semibold text-black hover:bg-primary-light rounded-xl transition-all duration-200">Log
                        In</a>
                    <a href="{{ route('register') }}"
                        class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-dark rounded-xl shadow-button hover:shadow-lg transition-all duration-200">Get
                        Started</a>
                @endauth
            </div>
            <button id="mobile-menu-btn" class="md:hidden p-2 text-black">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
            <div class="px-4 py-6 space-y-4">
                <a href="#how-it-works" class="block py-2 text-black/80 hover:text-primary font-medium">How It
                    Works</a>
                <a href="#services" class="block py-2 text-black/80 hover:text-primary font-medium">Services</a>
                <a href="#for-providers" class="block py-2 text-black/80 hover:text-primary font-medium">For
                    Providers</a>
                <a href="#about" class="block py-2 text-black/80 hover:text-primary font-medium">About</a>
                <div class="pt-4 space-y-3 border-t border-gray-200">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="block w-full text-center py-2 text-sm font-semibold text-white bg-primary rounded-xl">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="block w-full text-center py-2 text-sm font-semibold text-black hover:bg-primary-light rounded-xl">Log
                            In</a>
                        <a href="{{ route('register') }}"
                            class="block w-full text-center py-2 text-sm font-semibold text-white bg-primary rounded-xl">Get
                            Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const navbar = document.getElementById('navbar');

        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // Scroll effect
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                navbar.classList.remove('bg-transparent');
                navbar.classList.add('bg-white/80', 'backdrop-blur-md', 'shadow-sm');
            } else {
                navbar.classList.add('bg-transparent');
                navbar.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-sm');
            }
        });
    });
</script>
