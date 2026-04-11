<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin — Login</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .gradient-bg { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%); }
        .btn-gradient { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .btn-gradient:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(249, 115, 22, 0.45); }
        input:focus { border-color: #f97316 !important; box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.12); }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="gradient-bg min-h-screen min-h-dvh flex items-center justify-center p-4">
        <div class="glass rounded-3xl shadow-2xl p-8 md:p-10 w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl btn-gradient mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900">Super Admin</h1>
                <p class="text-gray-600 mt-2">Sign in to admin panel</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" x-data="{ email: '{{ old('email') }}', password: '', loading: false, get canSubmit() { return this.email.length > 0 && this.password.length > 0; }, async submit() { if (!this.canSubmit) return; this.loading = true; this.$el.submit(); } }" @submit.prevent="submit">
                @csrf
                <div class="mb-5">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" id="email" x-model="email" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all duration-200 outline-none" placeholder="admin@example.com" required autofocus>
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" id="password" x-model="password" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all duration-200 outline-none" placeholder="••••••••" required>
                </div>
                <button type="submit" :disabled="!canSubmit || loading" :class="{ 'opacity-70 cursor-not-allowed pointer-events-none': !canSubmit || loading }" class="w-full btn-gradient text-white font-semibold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-lg relative">
                    <span x-show="!loading" x-cloak>Sign In</span>
                    <span x-show="loading" x-cloak class="inline-flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Signing in...
                    </span>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
