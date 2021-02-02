@extends ('layouts/admin')
@if ($form = $p->formSmilies)
      <section id="fork-smls" class="f-admin">
        <h2>{!! __('Smilies head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formImages)
      <section id="fork-imgsm" class="f-admin">
        <h2>{!! __('Available images head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formUploadImage)
      <section id="fork-upimgsm" class="f-admin">
        <h2>{!! __('Upload image head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
