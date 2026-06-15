@props([
    'wireModel',
    'label' => 'Phone',
    'required' => false,
    'defaultDial' => '+971',
])

@php
    // Country list curated for the UAE insurance broker market: GCC + main
    // expat origins + a few Western codes. Add to this list — a 200-country
    // picker hurts more than it helps.
    $countries = [
        ['dial' => '+971', 'flag' => '🇦🇪', 'name' => 'United Arab Emirates'],
        ['dial' => '+966', 'flag' => '🇸🇦', 'name' => 'Saudi Arabia'],
        ['dial' => '+973', 'flag' => '🇧🇭', 'name' => 'Bahrain'],
        ['dial' => '+968', 'flag' => '🇴🇲', 'name' => 'Oman'],
        ['dial' => '+965', 'flag' => '🇰🇼', 'name' => 'Kuwait'],
        ['dial' => '+974', 'flag' => '🇶🇦', 'name' => 'Qatar'],
        ['dial' => '+91',  'flag' => '🇮🇳', 'name' => 'India'],
        ['dial' => '+92',  'flag' => '🇵🇰', 'name' => 'Pakistan'],
        ['dial' => '+880', 'flag' => '🇧🇩', 'name' => 'Bangladesh'],
        ['dial' => '+94',  'flag' => '🇱🇰', 'name' => 'Sri Lanka'],
        ['dial' => '+977', 'flag' => '🇳🇵', 'name' => 'Nepal'],
        ['dial' => '+63',  'flag' => '🇵🇭', 'name' => 'Philippines'],
        ['dial' => '+20',  'flag' => '🇪🇬', 'name' => 'Egypt'],
        ['dial' => '+962', 'flag' => '🇯🇴', 'name' => 'Jordan'],
        ['dial' => '+961', 'flag' => '🇱🇧', 'name' => 'Lebanon'],
        ['dial' => '+963', 'flag' => '🇸🇾', 'name' => 'Syria'],
        ['dial' => '+44',  'flag' => '🇬🇧', 'name' => 'United Kingdom'],
        ['dial' => '+1',   'flag' => '🇺🇸', 'name' => 'United States / Canada'],
    ];

    $countriesJson = json_encode($countries);
    $defaultDialJs = json_encode($defaultDial);
    $wireModelJs = json_encode($wireModel);
@endphp

<div
    x-data="{
        dial: '',
        national: '',
        search: '',
        countries: {{ $countriesJson }},
        get filtered() {
            const q = (this.search ?? '').trim().toLowerCase();
            if (! q) return this.countries;
            return this.countries.filter((c) =>
                c.name.toLowerCase().includes(q) ||
                c.dial.includes(q) ||
                c.dial.replace('+', '').includes(q)
            );
        },
        get current() {
            return this.countries.find((c) => c.dial === this.dial) ?? this.countries[0];
        },
        init() {
            const value = (this.$wire.get({{ $wireModelJs }}) ?? '').toString().trim();
            const codes = this.countries.map((c) => c.dial).sort((a, b) => b.length - a.length);
            const match = codes.find((c) => value.startsWith(c));
            if (match) {
                this.dial = match;
                this.national = value.slice(match.length).replace(/[^0-9]/g, '');
            } else {
                this.dial = {{ $defaultDialJs }};
                this.national = value.replace(/[^0-9]/g, '');
            }
            this.sync();
            this.$watch('dial', () => this.sync());
            this.$watch('national', () => this.sync());
        },
        sync() {
            const cleaned = (this.national ?? '').replace(/[^0-9]/g, '');
            this.$wire.set({{ $wireModelJs }}, cleaned ? this.dial + cleaned : '');
        },
        select(code) {
            this.dial = code;
            this.search = '';
            // Close the Flux dropdown by clicking outside it.
            document.body.click();
        },
    }">
    <flux:field>
        <flux:label :badge="$required ? 'Required' : null">{{ $label }}</flux:label>

        <div class="flex gap-2">
            {{-- Country code: Flux dropdown handles modal stacking +
                 viewport-aware positioning + scroll repositioning natively
                 via its <ui-dropdown> web component. --}}
            <flux:dropdown position="bottom" align="start">
                <button type="button"
                        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-2.5 text-sm shadow-xs hover:bg-zinc-50 dark:border-white/10 dark:bg-zinc-700 dark:hover:bg-zinc-600/75">
                    <span x-text="current.flag" class="text-base leading-none"></span>
                    <span x-text="current.dial" class="font-medium tabular-nums"></span>
                    <svg class="size-3 text-zinc-400" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/></svg>
                </button>

                <flux:menu class="!p-0 !w-72">
                    <div class="p-2 border-b border-zinc-100 dark:border-white/10">
                        <input type="text"
                               x-model="search"
                               x-on:click.stop
                               x-on:keydown.stop
                               placeholder="{{ __('Search country or code…') }}"
                               class="w-full rounded-md border border-zinc-200 bg-white px-2.5 py-1.5 text-sm placeholder-zinc-400 focus:border-zinc-400 focus:outline-none dark:border-white/10 dark:bg-zinc-900 dark:text-white" />
                    </div>
                    <ul class="max-h-64 overflow-y-auto py-1">
                        <template x-for="c in filtered" :key="c.dial + c.name">
                            <li>
                                <button type="button"
                                        x-on:click="select(c.dial)"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-white/5"
                                        :class="c.dial === dial ? 'bg-zinc-50 dark:bg-white/5 font-medium' : ''">
                                    <span x-text="c.flag" class="text-base leading-none"></span>
                                    <span x-text="c.name" class="flex-1 truncate"></span>
                                    <span x-text="c.dial" class="text-xs text-zinc-500 tabular-nums"></span>
                                </button>
                            </li>
                        </template>
                        <template x-if="filtered.length === 0">
                            <li class="px-3 py-4 text-center text-xs text-zinc-500">{{ __('No matches') }}</li>
                        </template>
                    </ul>
                </flux:menu>
            </flux:dropdown>

            <flux:input
                x-model="national"
                type="tel"
                inputmode="numeric"
                pattern="[0-9]*"
                placeholder="50 123 4567"
                class="flex-1"
                :required="$required"
            />
        </div>
        <flux:error name="{{ $wireModel }}" />
    </flux:field>
</div>
