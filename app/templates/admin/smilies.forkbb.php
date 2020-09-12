@extends ('layouts/admin')
@if ($form = $p->formSmilies)
      <section class="f-admin f-smilies-form">
        <h2>{!! __('Smilies head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formImages)
      <section class="f-admin f-images-list">
        <h2>{!! __('Available images head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formUploadImage)
      <section class="f-admin f-image-upload-form">
        <h2>{!! __('Upload image head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
