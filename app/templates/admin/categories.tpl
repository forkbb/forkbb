@section ('updown')
<a href="">UP</a> <a href="">DOWN</a>
@endsection
@extends ('layouts/admin')
      <section class="f-admin f-updatecategories-form">
        <h2>{!! __('Edit categories head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formUpdate)
  @include ('layouts/form')
@endif
        </div>
      </section>
