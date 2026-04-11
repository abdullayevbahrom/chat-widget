<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ChatWidget') }} — Login</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#f0f5ff', 100: '#e0eaff', 200: '#c7d5fe', 300: '#a4b6fc', 400: '#7b8ff8', 500: '#5b64f0', 600: '#4540e0', 700: '#3a35c8', 800: '#322da3', 900: '#2e2b80' },
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }
        .btn-gradient:hover {
            opacity: 0.95;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45);
        }
        input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="gradient-bg min-h-screen min-h-dvh flex items-center justify-center p-4">
        <div class="glass rounded-3xl shadow-2xl p-8 md:p-10 w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl btn-gradient mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900">ChatWidget</h1>
                <p class="text-gray-600 mt-2">Sign in to your account</p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200" x-data="{ show: true }" x-show="show" x-transition>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('tenant.login') }}" x-data="{
                email: '{{ old('email') }}',
                password: '',
                remember: {{ old('remember') ? 'true' : 'false' }},
                loading: false,
                get canSubmit() {
                    return this.email.length > 0 && this.password.length >= 8;
                },
                async submit() {
                    if (!this.canSubmit) return;
                    this.loading = true;
                    this.$el.submit();
                }
            }" @submit.prevent="submit">
                @csrf
                
                <!-- Email -->
                <div class="mb-5">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        x-model="email"
                        value="{{ old('email') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all duration-200 outline-none"
                        placeholder="you@example.com"
                        required
                        autofocus
                    >
                </div>

                <!-- Password -->
                <div class="mb-5">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        x-model="password"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all duration-200 outline-none"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            x-model="remember"
                            class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="!canSubmit || loading"
                    :class="{ 'opacity-70 cursor-not-allowed pointer-events-none': !canSubmit || loading }"
                    class="w-full btn-gradient text-white font-semibold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-lg relative"
                    x-text="loading ? 'Signing in...' : 'Sign In'"
                >
                </button>
            </form>

            <!-- Register Link -->
            <p class="mt-6 text-center text-sm text-gray-600">
                Don't have an account?
                <a href="{{ route('tenant.register') }}" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                    Create one now
                </a>
            </p>
        </div>
    </div>
</body>
</html>
