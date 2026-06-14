<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center font-bold text-xl mx-auto mb-3">
                {{ strtoupper(substr(config('app.name'), 0, 1)) }}
            </div>
            <h1 class="text-xl font-semibold text-gray-800">Recuperar contraseña</h1>
            <p class="text-sm text-gray-400 mt-1">Te enviaremos un código de 6 dígitos a tu email</p>
        </div>

        @if(session('info'))
            <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-lg">
                {{ session('info') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.send-link') }}"
              class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-bold text-gray-600 mb-1.5">Email</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       autofocus autocomplete="email"
                       class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300
                              {{ $errors->has('email') ? 'border-red-300' : 'border-gray-200' }}">
                @error('email')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                Enviar código
            </button>
        </form>

        <p class="text-center text-sm text-gray-400 mt-4">
            <a href="{{ route('login') }}" class="text-orange-500 hover:underline">Volver al login</a>
        </p>

    </div>

</body>
</html>
