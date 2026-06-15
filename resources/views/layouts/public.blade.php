<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Upload documents') }}</title>
    @vite(['resources/css/app.css'])
    @stack('scripts')
</head>
<body class="h-full antialiased text-zinc-900">
    <main class="min-h-full flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-2xl">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>
</body>
</html>
