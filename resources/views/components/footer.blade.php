<footer class="bg-white border-t border-gray-200 relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 pb-8 relative z-10">
        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-12 mb-12">
            <!-- Brand -->
            <div class="lg:col-span-2">
                <div class="text-2xl font-bold text-black mb-4">
                    <span class="gradient-text">{{ config('app.name') }}</span>
                </div>
                <p class="text-gray-600 mb-6 max-w-sm">
                    Connecting people with trusted artisans and service providers. Quality services, verified
                    professionals, one platform.
                </p>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 text-gray-600">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                        <span>support@allsers.com</span>
                    </div>
                    <!-- <div class="flex items-center gap-3 text-gray-600">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                            </path>
                        </svg>
                        <span>+234 902 222 2222</span>
                    </div> -->
                    <div class="flex items-center gap-3 text-gray-600">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Abuja, NG</span>
                    </div>
                </div>
            </div>
            <!-- Product -->
            <div>
                <h4 class="font-semibold text-black mb-4">Product</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-gray-600 hover:text-primary transition-colors duration-200">Find
                            Services</a></li>
                    <li><a href="#" class="text-gray-600 hover:text-primary transition-colors duration-200">List
                            Your
                            Business</a></li>
                    <li><a href="#"
                            class="text-gray-600 hover:text-primary transition-colors duration-200">Features</a>
                    </li>
                </ul>
            </div>
            <!-- Company -->
            <div>
                <h4 class="font-semibold text-black mb-4">Company</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-gray-600 hover:text-primary transition-colors duration-200">About
                            Us</a>
                    </li>
                    <li><a href="#"
                            class="text-gray-600 hover:text-primary transition-colors duration-200">Careers</a>
                    </li>
                </ul>
            </div>
            <!-- Support -->
            <div>
                <h4 class="font-semibold text-black mb-4">Support</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-gray-600 hover:text-primary transition-colors duration-200">Help
                            Center</a>
                    </li>
                    <li><a href="#"
                            class="text-gray-600 hover:text-primary transition-colors duration-200">Contact
                            Us</a>
                    </li>
                    <li><a href="{{ route('privacy') }}"
                            class="text-gray-600 hover:text-primary transition-colors duration-200">Privacy
                            Policy</a></li>
                    <li><a href="{{ route('terms') }}"
                            class="text-gray-600 hover:text-primary transition-colors duration-200">Terms
                            of
                            Service</a></li>
                </ul>
            </div>
        </div>
        <!-- Bottom -->
        <div class="pt-8 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="text-gray-600 text-sm">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="https://web.facebook.com/profile.php?id=61568367991669" aria-label="Facebook"
                    class="w-10 h-10 bg-primary-light rounded-full flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white transition-all duration-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                </a>
                <a href="https://www.tiktok.com/@_all_services" aria-label="Tiktok"
                    class="w-10 h-10 bg-primary-light rounded-full flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white transition-all duration-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.1z" />
                    </svg>
                </a>
                <a href="https://www.instagram.com/allserservices/" aria-label="Instagram"
                    class="w-10 h-10 bg-primary-light rounded-full flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white transition-all duration-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                    </svg>
                </a>
                <a href="#" aria-label="LinkedIn"
                    class="w-10 h-10 bg-primary-light rounded-full flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white transition-all duration-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
    </div>

    <div
        class="absolute bottom-0 left-0 right-0 flex justify-center pointer-events-none select-none opacity-[0.50] -mb-[5vw]">
        <h1 class="text-[28vw] font-black text-[#6a11cb] leading-none tracking-tighter">
            {{ config('app.name') }}
        </h1>
    </div>
</footer>
