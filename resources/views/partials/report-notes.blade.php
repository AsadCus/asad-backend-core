{{--
    Partial: report-notes
    Renders all notes that have non-empty rich-text descriptions.

    IMPORTANT: The parent template MUST compute $activeNotes before @include-ing
    this partial so the variable is available both here and in the footer condition:

        @php
            $activeNotes = collect($data['notes'] ?? [])
                ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                ->sortBy('sort_order')
                ->values();
        @endphp
        @include('partials.report-notes')

    The description field is raw Tiptap HTML — rendered as-is to preserve
    formatting: text-align (left/center/right), bold, italic, underline, etc.
--}}

@if ($activeNotes->isNotEmpty())
    <div class="report-notes">
        <p class="report-notes-heading">Notes</p>
        @foreach ($activeNotes as $note)
            <div class="note-item">{!! $note['description'] !!}</div>
        @endforeach
    </div>
@endif
