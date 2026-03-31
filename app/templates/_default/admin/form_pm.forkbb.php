@extends ('layouts/admin')
@if ($p->previewHtml)
      <section class="f-admin f-pm f-preview">
        <h2>{!! __('Post preview') !!}</h2>
        <div class="f-post-body">
          <div class="f-post-main">
            {!! $p->previewHtml !!}
          </div>
        </div>
      </section>
@endif
      <section class="f-admin f-pm f-post-form">
        <h2>{!! __($p->titleForm) !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
    @include ('layouts/form')
@endif
        </div>
      </section>
