@extends ('layouts/admin')
      <section id="fork-uploads" class="f-admin">
        <h2>{!! __('Uploads head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formUploads)
    @include ('layouts/form')
@endif
        </div>
      </section>
