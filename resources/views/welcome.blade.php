<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ChatWidget') }} — Real-time Chat Widget with Telegram</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#f0f5ff', 100: '#e0eaff', 200: '#c7d5fe', 300: '#a4b6fc', 400: '#7b8ff8', 500: '#5b64f0', 600: '#4540e0', 700: '#3a35c8', 800: '#322da3', 900: '#2e2b80' },
                        telegram: '#0088cc',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 0.5;
            }

            80%,
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.7s ease-out forwards;
            opacity: 0;
        }

        .animate-slide-right {
            animation: slideInRight 0.7s ease-out forwards;
            opacity: 0;
        }

        .animate-slide-left {
            animation: slideInLeft 0.7s ease-out forwards;
            opacity: 0;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        .delay-5 {
            animation-delay: 0.5s;
        }

        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%);
        }

        .gradient-cta {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        }

        .gradient-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        }

        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .pulse-dot {
            position: relative;
        }

        .pulse-dot::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .chat-bubble {
            position: relative;
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        .chat-bubble.visitor {
            background: #e0e7ff;
            color: #1e1b4b;
            border-bottom-left-radius: 4px;
        }

        .chat-bubble.admin {
            background: #4338ca;
            color: white;
            border-bottom-right-radius: 4px;
            margin-left: auto;
        }
    </style>
</head>

<body class="font-sans antialiased text-gray-800 bg-white">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass">
        <div class="container mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div
                    class="w-10 h-10 rounded-xl gradient-cta flex items-center justify-center text-white font-bold text-lg">
                    W</div>
                <span class="text-xl font-bold text-white">{{ config('app.name', 'ChatWidget') }}</span>
            </div>
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-white/80 hover:text-white transition">Features</a>
                <a href="#how-it-works" class="text-white/80 hover:text-white transition">How It Works</a>
                <a href="#pricing" class="text-white/80 hover:text-white transition">Pricing</a>
                <a href="/auth/login"
                    class="px-5 py-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-white font-medium transition">Sign
                    In</a>
                <a href="/auth/register"
                    class="px-5 py-2.5 rounded-lg bg-white text-brand-600 font-semibold hover:bg-brand-50 transition">Get
                    Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="gradient-hero min-h-screen flex items-center pt-20 relative overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-10 w-72 h-72 bg-brand-500/20 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-purple-500/15 rounded-full blur-3xl animate-float"
                style="animation-delay: 2s;"></div>
            <div class="absolute top-1/2 left-1/3 w-48 h-48 bg-cyan-400/10 rounded-full blur-3xl animate-float"
                style="animation-delay: 4s;"></div>
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Left: Content -->
                <div class="text-white">
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 mb-6 animate-fade-in-up">
                        <span class="pulse-dot w-2 h-2 bg-green-400 rounded-full"></span>
                        <span class="text-sm font-medium">Real-time Messaging + Telegram Integration</span>
                    </div>
                    <h1
                        class="text-5xl lg:text-6xl xl:text-7xl font-extrabold leading-tight mb-6 animate-fade-in-up delay-1">
                        Chat with Visitors<br />
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-300 to-purple-300">via
                            Telegram</span>
                    </h1>
                    <p class="text-xl text-white/80 mb-8 max-w-lg animate-fade-in-up delay-2">
                        Embed a beautiful live chat widget on your website. Receive messages on Telegram, reply
                        instantly, and grow your business — all from your phone.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 animate-fade-in-up delay-3">
                        <a href="/auth/register"
                            class="px-8 py-4 rounded-xl gradient-cta text-white font-semibold text-lg hover:opacity-90 transition shadow-lg shadow-brand-500/30">
                            🚀 Start Free — No Card Required
                        </a>
                    </div>
                    <div class="flex items-center gap-6 mt-8 animate-fade-in-up delay-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-white/70">Free plan available</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-white/70">Setup in 5 minutes</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Chat Widget Preview -->
                <div class="animate-slide-right delay-2">
                    <div class="relative max-w-md mx-auto">
                        <!-- Chat Window -->
                        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                            <!-- Header -->
                            <div class="gradient-cta px-6 py-4 flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white text-lg">
                                    💬</div>
                                <div>
                                    <div class="text-white font-semibold">Support Team</div>
                                    <div class="text-white/70 text-sm flex items-center gap-1">
                                        <span class="w-2 h-2 bg-green-400 rounded-full inline-block"></span>
                                        Online now
                                    </div>
                                </div>
                            </div>
                            <!-- Messages -->
                            <div class="p-6 space-y-4 bg-gray-50 min-h-[280px]">
                                <div class="chat-bubble visitor">
                                    Hi! I have a question about your product pricing. 🤔
                                </div>
                                <div class="chat-bubble admin">
                                    Hello! Of course, happy to help. We offer Free, Pro, and Enterprise plans. What
                                    would you like to know?
                                </div>
                                <div class="chat-bubble visitor">
                                    That's great! Can I try the Pro plan first?
                                </div>
                            </div>
                            <!-- Input -->
                            <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-3">
                                <input type="text" placeholder="Type a message..."
                                    class="flex-1 px-4 py-2.5 rounded-xl bg-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                                    disabled>
                                <button
                                    class="w-10 h-10 rounded-xl gradient-cta flex items-center justify-center text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- Telegram Notification Badge -->
                        <div
                            class="absolute -right-4 -top-4 bg-[#0088cc] text-white px-4 py-2 rounded-xl shadow-lg flex items-center gap-2 animate-float">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.48-.429-.013-1.252-.242-1.865-.442-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141.121.099.154.232.17.325.015.093.034.305.019.471z" />
                            </svg>
                            <span class="text-sm font-medium">Telegram</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
            <svg class="w-6 h-6 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span
                    class="inline-block px-4 py-1.5 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold mb-4">Features</span>
                <h2 class="text-4xl lg:text-5xl font-bold mb-4">Everything You Need to<br /><span
                        class="text-brand-600">Engage Visitors</span></h2>
                <p class="text-lg text-gray-600">Powerful real-time chat widget with Telegram integration — built for
                    modern businesses.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Feature 1 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-1">
                    <div class="w-14 h-14 rounded-2xl bg-brand-100 flex items-center justify-center text-2xl mb-6">💬
                    </div>
                    <h3 class="text-xl font-bold mb-3">Live Chat Widget</h3>
                    <p class="text-gray-600">Beautiful, customizable chat widget that embeds seamlessly into any website
                        with a single script tag.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-2">
                    <div class="w-14 h-14 rounded-2xl bg-sky-100 flex items-center justify-center text-2xl mb-6">✈️
                    </div>
                    <h3 class="text-xl font-bold mb-3">Telegram Integration</h3>
                    <p class="text-gray-600">Receive visitor messages directly on Telegram. Reply from your phone —
                        responses appear in real-time on the widget.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-3">
                    <div class="w-14 h-14 rounded-2xl bg-green-100 flex items-center justify-center text-2xl mb-6">⚡
                    </div>
                    <h3 class="text-xl font-bold mb-3">Instant Delivery</h3>
                    <p class="text-gray-600">Messages appear instantly for both visitors and admins — no page refresh
                        needed.</p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-1">
                    <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center text-2xl mb-6">👥
                    </div>
                    <h3 class="text-xl font-bold mb-3">Multi-Tenant Platform</h3>
                    <p class="text-gray-600">Each tenant gets isolated workspace with their own projects, domains,
                        widget keys, and Telegram bots.</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-2">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center text-2xl mb-6">📊
                    </div>
                    <h3 class="text-xl font-bold mb-3">Visitor Analytics</h3>
                    <p class="text-gray-600">Track visitor sessions, browser info, device type, and behavior — all
                        GDPR-compliant with encrypted IPs.</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-2xl p-8 shadow-sm card-hover animate-fade-in-up delay-3">
                    <div class="w-14 h-14 rounded-2xl bg-rose-100 flex items-center justify-center text-2xl mb-6">🔒
                    </div>
                    <h3 class="text-xl font-bold mb-3">Enterprise Security</h3>
                    <p class="text-gray-600">Domain whitelisting, rate limiting, XSS protection, CSRF defense, and
                        encrypted bot tokens — production-ready.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-24 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span
                    class="inline-block px-4 py-1.5 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold mb-4">How
                    It Works</span>
                <h2 class="text-4xl lg:text-5xl font-bold mb-4">Setup in <span class="text-brand-600">4 Simple
                        Steps</span></h2>
                <p class="text-lg text-gray-600">From registration to live chat on your website — it takes less than 5
                    minutes.</p>
            </div>

            <div class="max-w-5xl mx-auto">
                <!-- Step 1 -->
                <div class="grid md:grid-cols-2 gap-12 items-center mb-20">
                    <div class="animate-slide-left delay-1">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-cta text-white text-2xl font-bold mb-6">
                            1</div>
                        <h3 class="text-2xl font-bold mb-4">Create Your Account</h3>
                        <p class="text-gray-600 text-lg mb-4">Sign up for free and get your own tenant workspace. No
                            credit card required.</p>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Free plan with core features</li>
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Isolated workspace for your data</li>
                        </ul>
                    </div>
                    <div class="animate-slide-right delay-2">
                        <div class="bg-gray-50 rounded-2xl p-8 border border-gray-200">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-brand-100 flex items-center justify-center text-brand-600">
                                        📧</div>
                                    <div class="flex-1 h-3 bg-gray-200 rounded-full"></div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-brand-100 flex items-center justify-center text-brand-600">
                                        🔑</div>
                                    <div class="flex-1 h-3 bg-gray-200 rounded-full"></div>
                                </div>
                                <button class="w-full py-3 rounded-xl gradient-cta text-white font-semibold mt-4">Create
                                    Account</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="grid md:grid-cols-2 gap-12 items-center mb-20">
                    <div class="md:order-1 animate-slide-left delay-2">
                        <div class="bg-gray-50 rounded-2xl p-6 border border-gray-200">
                            <!-- Bot Connection Card -->
                            <div class="flex items-center gap-4 p-4 bg-white rounded-xl shadow-sm">
                                <div
                                    class="w-12 h-12 rounded-full bg-sky-500 flex items-center justify-center text-white">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.48-.429-.013-1.252-.242-1.865-.442-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141.121.099.154.232.17.325.015.093.034.305.019.471z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-800">@YourBot</div>
                                    <div class="text-sm text-green-600 flex items-center gap-1">
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        Connected
                                    </div>
                                </div>
                            </div>
                            <!-- Message Preview -->
                            <div class="mt-4 p-4 bg-sky-50 rounded-xl text-sm text-sky-800">
                                💬 <strong>New message:</strong> "Hi, I have a question..."<br />
                                <span class="text-sky-600 italic">Reply directly from Telegram</span>
                            </div>
                        </div>
                    </div>
                    <div class="md:order-2 animate-slide-right delay-1">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-cta text-white text-2xl font-bold mb-6">
                            2</div>
                        <h3 class="text-2xl font-bold mb-4">Connect Your Telegram Bot</h3>
                        <p class="text-gray-600 text-lg mb-4">Create a bot via @BotFather, paste the token, and you're
                            ready to receive messages.</p>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Encrypted token storage</li>
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Automatic webhook setup</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="grid md:grid-cols-2 gap-12 items-center mb-20">
                    <div class="animate-slide-left delay-1">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-cta text-white text-2xl font-bold mb-6">
                            3</div>
                        <h3 class="text-2xl font-bold mb-4">Add Your Website Domain</h3>
                        <p class="text-gray-600 text-lg mb-4">Register your website domain for security whitelisting and
                            generate a widget key.</p>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Domain verification process</li>
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Unique widget key per project</li>
                        </ul>
                    </div>
                    <div class="animate-slide-right delay-2">
                        <div class="bg-gray-50 rounded-2xl p-8 border border-gray-200">
                            <div class="bg-white rounded-xl p-4 border border-gray-200">
                                <div class="text-sm text-gray-500 mb-2">Your widget embed code:</div>
                                <code
                                    class="text-xs bg-gray-100 p-3 rounded-lg block break-all">&lt;script src="{{ asset('js/widget.js') }}"&gt;&lt;/script&gt;</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div class="md:order-2 animate-slide-right delay-1">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gradient-cta text-white text-2xl font-bold mb-6">
                            4</div>
                        <h3 class="text-2xl font-bold mb-4">Start Chatting in Real-Time</h3>
                        <p class="text-gray-600 text-lg mb-4">Visitors message you through the widget, you reply via
                            Telegram — it's that simple.</p>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Instant message delivery</li>
                            <li class="flex items-center gap-2"><svg class="w-5 h-5 text-green-500" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg> Typing indicators & online status</li>
                        </ul>
                    </div>
                    <div class="md:order-1 animate-slide-left delay-2">
                        <div class="bg-gray-50 rounded-2xl p-8 border border-gray-200">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-brand-200 flex items-center justify-center text-sm">
                                        V</div>
                                    <div class="chat-bubble visitor text-sm">Hello! I need help with my order. 📦</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="chat-bubble admin text-sm ml-auto">Sure! Let me check that for you. ✈️
                                    </div>
                                    <div
                                        class="w-8 h-8 rounded-full bg-sky-500 flex items-center justify-center text-sm text-white">
                                        A</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How Messages Flow Section -->
    <section class="py-24 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span
                    class="inline-block px-5 py-2 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold mb-6">How
                    It Works</span>
                <h2 class="text-5xl lg:text-6xl font-extrabold mb-4">Seamless <span class="text-brand-600">Message
                        Flow</span></h2>
                <p class="text-lg text-gray-500">From your website visitor to your Telegram — and back in seconds.</p>
            </div>

            <div class="max-w-7xl mx-auto">
                <div class="bg-white rounded-3xl p-12 shadow-sm border border-gray-100 overflow-x-auto">
                    <!-- Flow Diagram - Single Row -->
                    <div class="flex items-center justify-center gap-3 text-center min-w-fit">
                        <!-- Visitor -->
                        <div class="flex items-center gap-3 px-6 py-4 bg-brand-50 rounded-2xl flex-shrink-0">
                            <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                            </svg>
                            <div class="text-left">
                                <div class="font-bold text-gray-800">Visitor</div>
                                <div class="text-gray-500 text-xs">Your Website</div>
                            </div>
                        </div>

                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>

                        <!-- Chat Widget -->
                        <div class="flex items-center gap-3 px-6 py-4 bg-purple-50 rounded-2xl flex-shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <div class="text-left">
                                <div class="font-bold text-gray-800">Chat Widget</div>
                                <div class="text-gray-500 text-xs">Live Chat</div>
                            </div>
                        </div>

                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>

                        <!-- Platform -->
                        <div class="flex items-center gap-3 px-6 py-4 bg-amber-50 rounded-2xl flex-shrink-0">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div class="text-left">
                                <div class="font-bold text-gray-800">Platform</div>
                                <div class="text-gray-500 text-xs">Message Router</div>
                            </div>
                        </div>

                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>

                        <!-- Telegram -->
                        <div class="flex items-center gap-3 px-6 py-4 bg-sky-50 rounded-2xl flex-shrink-0">
                            <svg class="w-6 h-6 text-sky-600" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.48-.429-.013-1.252-.242-1.865-.442-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141.121.099.154.232.17.325.015.093.034.305.019.471z" />
                            </svg>
                            <div class="text-left">
                                <div class="font-bold text-gray-800">Telegram</div>
                                <div class="text-gray-500 text-xs">Bot API</div>
                            </div>
                        </div>

                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>

                        <!-- Client (Admin o'rniga, bir qatorda) -->
                        <div class="flex items-center gap-3 px-6 py-4 bg-green-50 rounded-2xl flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <div class="text-left">
                                <div class="font-bold text-gray-800">Client</div>
                                <div class="text-gray-500 text-xs">Reply via Telegram</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <span
                    class="inline-block px-4 py-1.5 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold mb-4">Pricing</span>
                <h2 class="text-4xl lg:text-5xl font-bold mb-4">Simple, <span class="text-brand-600">Transparent</span>
                    Pricing</h2>
                <p class="text-lg text-gray-600">Start free, upgrade when you're ready. No hidden fees.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Free -->
                <div class="bg-white rounded-2xl p-8 border-2 border-gray-200 card-hover">
                    <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Free</div>
                    <div class="text-5xl font-extrabold mb-2">$0</div>
                    <div class="text-gray-500 mb-6">Forever free</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> 1 Project</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Telegram Integration</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Basic Analytics</li>
                        <li class="flex items-center gap-2 text-gray-400"><svg class="w-5 h-5" fill="currentColor"
                                viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg> Custom Branding</li>
                    </ul>
                    <a href="/auth/register"
                        class="block text-center px-6 py-3 rounded-xl border-2 border-gray-200 text-gray-700 font-semibold hover:border-brand-400 hover:text-brand-600 transition">Get
                        Started</a>
                </div>

                <!-- Pro -->
                <div
                    class="bg-white rounded-2xl p-8 border-2 border-brand-500 shadow-lg shadow-brand-500/10 card-hover relative">
                    <div
                        class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full gradient-cta text-white text-sm font-semibold">
                        Popular</div>
                    <div class="text-sm font-semibold text-brand-600 uppercase tracking-wide mb-2">Pro</div>
                    <div class="text-5xl font-extrabold mb-2">$29</div>
                    <div class="text-gray-500 mb-6">per month</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Unlimited Projects</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Priority Support</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Advanced Analytics</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Custom Branding</li>
                    </ul>
                    <a href="/auth/register"
                        class="block text-center px-6 py-3 rounded-xl gradient-cta text-white font-semibold hover:opacity-90 transition shadow-lg shadow-brand-500/30">Start
                        Free Trial</a>
                </div>

                <!-- Enterprise -->
                <div class="bg-white rounded-2xl p-8 border-2 border-gray-200 card-hover">
                    <div class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Enterprise</div>
                    <div class="text-5xl font-extrabold mb-2">Custom</div>
                    <div class="text-gray-500 mb-6">contact us</div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> White Label Solution</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Dedicated Support</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> SLA Agreement</li>
                        <li class="flex items-center gap-2 text-gray-600"><svg class="w-5 h-5 text-green-500"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg> Custom Integrations</li>
                    </ul>
                    <a href="/auth/register"
                        class="block text-center px-6 py-3 rounded-xl border-2 border-gray-200 text-gray-700 font-semibold hover:border-brand-400 hover:text-brand-600 transition">Contact
                        Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="gradient-hero py-24 relative overflow-hidden">
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-10 right-20 w-64 h-64 bg-brand-500/20 rounded-full blur-3xl animate-float"></div>
            <div class="absolute bottom-10 left-20 w-80 h-80 bg-purple-500/15 rounded-full blur-3xl animate-float"
                style="animation-delay: 3s;"></div>
        </div>
        <div class="container mx-auto px-6 text-center relative z-10">
            <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">Ready to Engage Your Visitors?</h2>
            <p class="text-xl text-white/80 mb-10 max-w-2xl mx-auto">Join thousands of businesses using ChatWidget to
                connect with their customers in real-time via Telegram.</p>
            <a href="/auth/register"
                class="inline-block px-10 py-4 rounded-xl bg-white text-brand-600 font-bold text-lg hover:bg-brand-50 transition shadow-xl">
                🎯 Get Started Free — Setup in 5 Minutes
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div
                            class="w-8 h-8 rounded-lg gradient-cta flex items-center justify-center text-white font-bold">
                            W</div>
                        <span class="text-lg font-bold text-white">{{ config('app.name', 'ChatWidget') }}</span>
                    </div>
                    <p class="text-sm">Real-time chat widget platform with Telegram integration. Connect with your
                        website visitors instantly.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white transition">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition">How It Works</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Resources</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/app" class="hover:text-white transition">Tenant Portal</a></li>
                        <li><a href="/widget/embed" class="hover:text-white transition">Widget Demo</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-sm">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'ChatWidget') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>

</html>