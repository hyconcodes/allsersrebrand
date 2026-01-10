<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Privacy Policy - {{ config('app.name') }}</title>
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
                    Privacy <span class="gradient-text">Policy</span>
                </h1>
                <p class="text-zinc-600 font-medium">Last updated: {{ date('F j, Y') }}</p>
            </div>

            <div
                class="prose prose-zinc prose-lg max-w-none bg-white rounded-[40px] p-8 sm:p-12 shadow-xl border border-zinc-100">
                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">1. Introduction</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        Welcome to Allsers. We respect your privacy and are committed to protecting your personal data.
                        This privacy policy will inform you about how we look after your personal data when you visit
                        our website (regardless of where you visit it from) and tell you about your privacy rights and
                        how the law protects you.
                    </p>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">2. The Data We Collect</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        Personal data, or personal information, means any information about an individual from which
                        that person can be identified. We may collect, use, store and transfer different kinds of
                        personal data about you which we have grouped together as follows:
                    </p>
                    <ul class="list-disc pl-6 text-zinc-600 space-y-2">
                        <li><strong>Identity Data</strong> includes first name, last name, username or similar
                            identifier.</li>
                        <li><strong>Contact Data</strong> includes email address and telephone numbers.</li>
                        <li><strong>Technical Data</strong> includes internet protocol (IP) address, your login data,
                            browser type and version, time zone setting and location, browser plug-in types and
                            versions, operating system and platform, and other technology on the devices you use to
                            access this website.</li>
                        <li><strong>Profile Data</strong> includes your username and password, services provided,
                            interests, preferences, feedback and survey responses.</li>
                    </ul>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">3. How We Use Your Personal Data</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        We will only use your personal data when the law allows us to. Most commonly, we will use your
                        personal data in the following circumstances:
                    </p>
                    <ul class="list-disc pl-6 text-zinc-600 space-y-2">
                        <li>Where we need to perform the contract we are about to enter into or have entered into with
                            you.</li>
                        <li>Where it is necessary for our legitimate interests (or those of a third party) and your
                            interests and fundamental rights do not override those interests.</li>
                        <li>Where we need to comply with a legal obligation.</li>
                    </ul>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">4. Data Security</h2>
                    <p class="text-zinc-600 leading-relaxed mb-4">
                        We have put in place appropriate security measures to prevent your personal data from being
                        accidentally lost, used or accessed in an unauthorized way, altered or disclosed. In addition,
                        we limit access to your personal data to those employees, agents, contractors and other third
                        parties who have a business need to know.
                    </p>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-zinc-900 mb-4">5. Contact Us</h2>
                    <p class="text-zinc-600 leading-relaxed mb-0">
                        If you have any questions about this privacy policy or our privacy practices, please contact us
                        at:
                    </p>
                    <p class="text-primary font-bold mt-2">support@allsers.com</p>
                </section>
            </div>
        </div>
    </main>

    <x-footer />
</body>

</html>
