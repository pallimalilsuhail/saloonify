@extends('layouts.public', ['title' => __('Link expired')])

@section('content')
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden text-center">
        <div class="px-6 sm:px-8 py-10">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100">
                <svg class="size-7 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-semibold text-zinc-900">
                {{ __('This upload link is no longer active') }}
            </h1>
            <p class="mt-2 text-sm text-zinc-600">
                {{ __('It may have expired or been cancelled.') }}
            </p>
        </div>
        <div class="border-t border-zinc-100 bg-zinc-50/60 px-5 py-3">
            <p class="text-xs text-zinc-500">
                {{ __('Reply to the original message and ask :business for a new link.', ['business' => $session->businessName]) }}
            </p>
        </div>
    </div>
@endsection
