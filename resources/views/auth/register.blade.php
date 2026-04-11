<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ChatWidget') }} — Register</title>
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
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .gradient-bg { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%); }
        .btn-gradient { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); }
        .btn-gradient:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45); }
        input:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
        .password-strength { height: 4px; border-radius: 2px; transition: all 0.3s; }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="gradient-bg min-h-screen min-h-dvh flex items-center justify-center p-4">
        <div class="glass rounded-3xl shadow-2xl p-8 md:p-10 w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl btn-gradient mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-extrabold text-gray-900">ChatWidget</h1>
                <p class="text-gray-600 mt-2">Create your free account</p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <ul class="text-sm text-red-700 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Register Form -->
            <form method="POST" action="{{ route('tenant.register') }}" x-data="{
                email: '{{ old('email') }}',
                password: '',
                password_confirmation: '',
                loading: false,
                get passwordStrength() {
                    let strength = 0;
                    if (this.password.length >= 8) strength++;
                    if (/[a-z]/.test(this.password) && /[A-Z]/.test(this.password)) strength++;
                    if (/\d/.test(this.password)) strength++;
                    if (/[^a-zA-Z0-9]/.test(this.password)) strength++;
                    return strength;
                },
                get passwordStrengthText() {
                    const texts = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
                    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500', 'bg-emerald-500'];
                    return { text: texts[this.passwordStrength] || '', color: colors[this.passwordStrength] || 'bg-gray-200' };
                },
                get passwordsMatch() {
                    return this.password_confirmation === this.password;
                },
                get canSubmit() {
                    return this.email.length > 0 && 
                           this.password.length >= 8 && 
                           this.password === this.password_confirmation;
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
                        placeholder="Min. 8 characters"
                        required
                    >
                    @if (old('password'))
                        <div class="mt-2">
                            <div class="flex gap-1 mb-1">
                                <template x-for="i in 4">
                                    <div class="password-strength flex-1" :class="i <= passwordStrength ? passwordStrengthText.color : 'bg-gray-200'"></div>
                                </template>
                            </div>
                            <p class="text-xs text-gray-500" x-text="passwordStrengthText.text"></p>
                        </div>
                    @endif
                </div>

                <!-- Confirm Password -->
                <div class="mb-5">
                    <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                    <input 
                        type="password" 
                        name="password_confirmation" 
                        id="password_confirmation" 
                        x-model="password_confirmation"
                        :class="{ 'border-green-500': passwordsMatch && password_confirmation.length > 0, 'border-red-500': !passwordsMatch && password_confirmation.length > 0 }"
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 transition-all duration-200 outline-none"
                        placeholder="Re-enter password"
                        required
                    >
                    <p x-show="!passwordsMatch && password_confirmation.length > 0" class="mt-1 text-xs text-red-600">Passwords do not match</p>
                    <p x-show="passwordsMatch && password_confirmation.length > 0" class="mt-1 text-xs text-green-600">✓ Passwords match</p>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="!canSubmit || loading"
                    :class="{ 'opacity-70 cursor-not-allowed pointer-events-none': !canSubmit || loading }"
                    class="w-full btn-gradient text-white font-semibold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-lg relative"
                >
                    <span x-show="!loading" x-cloak>Create Account</span>
                    <span x-show="loading" x-cloak class="inline-flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </button>
            </form>

            <!-- Login Link -->
            <p class="mt-6 text-center text-sm text-gray-600">
                Already have an account?
                <a href="{{ route('tenant.login') }}" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                    Sign in
                </a>
            </p>
        </div>
    </div>
</body>
</html>
