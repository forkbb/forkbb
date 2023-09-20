@section ('og:image') @if ($p->ogImageUrl) <img class="f-og-img" src="{{ $p->ogImageUrl }}" alt="{{ \basename($p->ogImageUrl) }}"> @endif @endsection
@extends ('layouts/admin')
      <section class="f-admin @if ($p->classForm) f-{{ \implode('-form f-', $p->classForm) }}-form @endif">
        <h2>{!! __($p->titleForm) !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
    @include ('layouts/form')
@endif
        </div>
      </section>
