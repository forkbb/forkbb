@extends ('layouts/main')
@if ($p->back)
    <div class="f-backlink">
      <p><a href="{!! $p->fRootLink !!}" onclick="window.history.back(); return false;">{!! __('Go back') !!}</a></p>
    </div>
@endif
