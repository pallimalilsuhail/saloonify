@extends('layouts.public', ['title' => __('Link not found')])

@section('content')
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden text-center">
        <div class="px-6 sm:px-8 py-10">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100">
                <svg class="size-7 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-semibold text-zinc-900">
                {{ __('Upload link not found') }}
            </h1>
            <p class="mt-2 text-sm text-zinc-600">
                {{ __('The link may be incomplete or the page may have been mistyped.') }}
            </p>
        </div>
        <div class="border-t border-zinc-100 bg-zinc-50/60 px-5 py-3">
            <p class="text-xs text-zinc-500">
                {{ __('Open the link directly from the message you received, or ask the business for a new one.') }}
            </p>
        </div>
    </div>
@endsection
