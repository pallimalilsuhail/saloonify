@extends('layouts.public', ['title' => __('Documents received')])

@section('content')
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden text-center">
        <div class="px-6 sm:px-8 py-10">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-green-100">
                <svg class="size-7 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-semibold text-zinc-900">
                {{ __('Documents already received') }}
            </h1>
            <p class="mt-2 text-sm text-zinc-600">
                {{ __(':business has your files. You can close this page.', ['business' => $session->businessName]) }}
            </p>
        </div>
        <div class="border-t border-zinc-100 bg-zinc-50/60 px-5 py-3">
            <p class="text-xs text-zinc-500">
                {{ __('Need to send more? Ask :business to send you a fresh link.', ['business' => $session->businessName]) }}
            </p>
        </div>
    </div>
@endsection
