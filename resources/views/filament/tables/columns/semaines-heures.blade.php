{{-- resources/views/filament/tables/columns/semaines-heures.blade.php --}}
<div>
    @php
    $data = $getState();
    $content = '';
    
    if (is_array($data)) {
        foreach ($data as $item) {
            $semaine = $item['semaine'];
            $heures = $item['heures'];
            $validated = $item['validated'];
            $heures_supp = $item['heures_supp'] ?? [];
            
            $validatedIcon = $validated
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#22c55e" class="inline-block mb-1 w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                   </svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#ef4444" class="inline-block mb-1 w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                   </svg>';
            
            $content .= "<span class='font-medium'>" . __('pages.tables.columns.semaines_heures.semaine') . " {$semaine->numero} :</span> {$heures} h $validatedIcon<br>";
            
            if (!empty($heures_supp)) {
                $content .= "<ul class='text-gray-600 text-sm italic'>";
                foreach ($heures_supp as $hs) {
                    $content .= "<li>- " . e($hs->nb_heures) . "h :  " . e($hs->commentaire) . "</li>";
                }
                $content .= "</ul>";
            }
        }
    }
    @endphp
    
    <div class="flex items-center">
        <div class="text-gray-600 w-full">
            @if(!empty($content))
                {!! $content !!}
            @else
                <div class="flex items-center space-x-2 p-2 text-gray-400 text-sm">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" />
                        <line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                    <span>{{ __('pages.tables.columns.semaines_heures.no_results') }}</span>
                </div>
            @endif
        </div>
    </div>
</div>