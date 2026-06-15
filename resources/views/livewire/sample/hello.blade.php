<?php

use function Livewire\Volt\{state};

state(['name' => 'World']);

?>

<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-semibold mb-2">Hello, {{ $name }}.</h1>
    <p class="text-gray-600">Volt + Livewire scaffold working.</p>
    <div class="mt-4 flex gap-2">
        <input wire:model.live="name" type="text" class="rounded border-gray-300 px-3 py-2 text-sm" />
    </div>
</div>
