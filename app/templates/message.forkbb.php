@extends ('layouts/main')
    <section class="f-main f-message">
      <h2>{!! __('Info') !!}</h2>
      <p>{!! $p->message !!}</p>
@if ($p->back)
      <p><a href="{!! $p->fRootLink !!}" onclick="window.history.back(); return false;">{!! __('Go back') !!}</a></p>
@endif
    </section>
