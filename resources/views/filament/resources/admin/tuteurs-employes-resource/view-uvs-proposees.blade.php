<style>
    svg {
        width: 3rem;
        height: 3rem;
    }
</style>

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Section pour les tuteurs employés --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        UVs proposées par les tuteur.ice.s employé.e.s
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Liste des UVs proposées par les tuteur.ice.s employés et tuteur.ice.s employé.e.s privilégié.e.s
                    </p>
                </div>
            </div>
            
            <div class="fi-section-content-ctn overflow-hidden">
                <div class="fi-section-content p-6">
                    @if($this->getEmployedTutorsData()->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Prénom</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Nom</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Rôle</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">UVs proposées</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->getEmployedTutorsData() as $tutor)
                                        <tr class="">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $tutor->firstName ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $tutor->lastName ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                @if($tutor->role === App\Enums\Roles::EmployedTutor->value)
                                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">
                                                        Tuteur.ice employé.e
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 dark:bg-purple-400/10 dark:text-purple-400 dark:ring-purple-400/30">
                                                        Tuteur.ice employé.e privilégié.e
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <div class="flex flex-wrap gap-1">
                                                    @if($tutor->proposedUvs)
                                                        @foreach($tutor->proposedUvs as $uv)
                                                            <span class="inline-flex items-center gap-x-1 rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                                                {{ $uv->code }} - {{ $uv->intitule }}
                                                                <button
                                                                    type="button"
                                                                    wire:click="removeUv({{ $tutor->id }}, '{{ $uv->code }}')"
                                                                    wire:confirm="Retirer l'UV {{ $uv->code }} à {{ $tutor->firstName }} {{ $tutor->lastName }} ?"
                                                                    class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400"
                                                                    title="Retirer cette UV"
                                                                >
                                                                    &times;
                                                                </button>
                                                            </span>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20">
                                                    {{ $tutor->proposedUvs->count() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-gray-400 dark:text-gray-500">
                                <svg class="mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Aucune UV proposée</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aucun tuteur employé ne propose d'UV actuellement</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section pour les tuteur.ice.s bénévoles --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        UVs proposées par des bénévoles
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Liste des UVs proposées par les tuteur.ice.s bénévoles
                    </p>
                </div>
            </div>
            
            <div class="fi-section-content-ctn overflow-hidden">
                <div class="fi-section-content p-6">
                    @if($this->getVolunteerTutorsData()->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Prénom</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Nom</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">UVs proposées</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($this->getVolunteerTutorsData() as $tutor)
                                        <tr class="">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $tutor->firstName ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $tutor->lastName ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($tutor->proposedUvs as $uv)
                                                        <span class="inline-flex items-center gap-x-1 rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                                            {{ $uv->code }} - {{ $uv->intitule }}
                                                            <button
                                                                type="button"
                                                                wire:click="removeUv({{ $tutor->id }}, '{{ $uv->code }}')"
                                                                wire:confirm="Retirer l'UV {{ $uv->code }} à {{ $tutor->firstName }} {{ $tutor->lastName }} ?"
                                                                class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400"
                                                                title="Retirer cette UV"
                                                            >
                                                                &times;
                                                            </button>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/20">
                                                    {{ $tutor->proposedUvs->count() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-gray-400 dark:text-gray-500">
                                <svg class="mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Aucune UV proposée</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aucun tuteur.ice bénévole ne propose d'UV actuellement</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
