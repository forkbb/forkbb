@section ('og:image') @if ($p->ogImageUrl) <img class="f-og-img" src="{{ $p->ogImageUrl }}" alt="{{ \basename($p->ogImageUrl) }}"> @endif @endsection
@extends ('layouts/admin')
      <section @class(['f-admin', [$p->classForm, 'f-', '-form']])>
        <h2>{!! __($p->titleForm) !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
    @include ('layouts/form')
@endif
        </div>
      </section>
