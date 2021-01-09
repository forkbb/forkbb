@extends ('layouts/main')
@if ($p->back)
    <div class="f-backlink">
      <p><a class="f-go-back" href="{{ $p->fRootLink }}">{!! __('Go back') !!}</a></p>
    </div>
@endif
