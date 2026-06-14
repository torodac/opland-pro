<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center font-bold text-xl mx-auto mb-3">
                {{ strtoupper(substr(config('app.name'), 0, 1)) }}
            </div>
            <h1 class="text-xl font-semibold text-gray-800">Nueva contraseña</h1>
            <p class="text-sm text-gray-400 mt-1">Elige una nueva contraseña para tu cuenta</p>
        </div>

        <form method="POST" action="{{ route('password.reset') }}"
              class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div>
                <label for="password" class="block text-xs font-bold text-gray-600 mb-1.5">Nueva contraseña</label>
                <input type="password" id="password" name="password"
                       autofocus autocomplete="new-password"
                       class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300
                              {{ $errors->has('password') ? 'border-red-300' : 'border-gray-200' }}">
                @error('password')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-gray-600 mb-1.5">Repite la contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       autocomplete="new-password"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                Guardar contraseña
            </button>
        </form>

        <p class="text-center text-sm text-gray-400 mt-4">
            <a href="{{ route('login') }}" class="hover:underline">Volver al login</a>
        </p>

    </div>

</body>
</html>
