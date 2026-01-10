<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Terms of Service - {{ config('app.name') }}</title>
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
                    }
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

        .glass {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body class="font-sans antialiased bg-zinc-50 text-black">
    <x-navbar />

    <main class="pt-32 pb-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-black text-zinc-900 mb-4 tracking-tight">
                    Terms of <span class="gradient-text">Service</span>
                </h1>
                <p class="text-zinc-600 font-medium">Last updated: {{ date('F j, Y') }}</p>
            </div>

            <div
                class="prose prose-zinc prose-lg max-w-none bg-white rounded-[40px] p-8 sm:p-12 shadow-xl border border-zinc-100">
                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">1. Acceptance of Terms</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        By accessing or using Allsers, you agree to be bound by these Terms of Service and all
                        applicable laws and regulations. If you do not agree with any of these terms, you are prohibited
                        from using or accessing this site.
                    </p>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">2. Use License</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        Permission is granted to temporarily download one copy of the materials on Allsers' website for
                        personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer
                        of title, and under this license you may not:
                    </p>
                    <ul class="list-disc pl-6 text-zinc-600 space-y-2">
                        <li>Modify or copy the materials;</li>
                        <li>Use the materials for any commercial purpose, or for any public display;</li>
                        <li>Attempt to decompile or reverse engineer any software contained on Allsers' website;</li>
                        <li>Remove any copyright or other proprietary notations from the materials; or</li>
                        <li>Transfer the materials to another person or "mirror" the materials on any other server.</li>
                    </ul>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">3. User Obligations</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        Users of Allsers (both service providers and customers) agree to:
                    </p>
                    <ul class="list-disc pl-6 text-zinc-600 space-y-2">
                        <li>Provide accurate and truthful information.</li>
                        <li>Maintain the security of their account.</li>
                        <li>Not engage in any fraudulent or illegal activities on the platform.</li>
                        <li>Respect other users and communicate professionally.</li>
                    </ul>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">4. Disclaimer</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        The materials on Allsers' website are provided on an 'as is' basis. Allsers makes no warranties,
                        expressed or implied, and hereby disclaims and negates all other warranties including, without
                        limitation, implied warranties or conditions of merchantability, fitness for a particular
                        purpose, or non-infringement of intellectual property or other violation of rights.
                    </p>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">5. Limitation of Liability</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        In no event shall Allsers or its suppliers be liable for any damages (including, without
                        limitation, damages for loss of data or profit, or due to business interruption) arising out of
                        the use or inability to use the materials on Allsers' website.
                    </p>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">6. Contact Us</h2>
                    <p class="text-zinc-600 leading-relaxed mb-0">
                        If you have any questions about these Terms of Service, please contact us at:
                    </p>
                    <p class="text-primary font-bold mt-2">support@allsers.com</p>
                </section>
            </div>
        </div>
    </main>

    <x-footer />
</body>

</html>
