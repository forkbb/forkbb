@extends ('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Add group subhead') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formNew)
  @include ('layouts/form')
@endif
        </div>
      </section>
      <section class="f-admin">
        <h2>{!! __('Default group subhead') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formDefault)
  @include ('layouts/form')
@endif
        </div>
      </section>
      <section class="f-admin">
        <h2>{!! __('Edit groups subhead') !!}</h2>
        <div>
          <p>{!! __('Edit groups info') !!}</p>
          <ol class="f-grlist">
@foreach ($p->groupsList as $cur)
            <li>
              <a href="{!! $cur[1] !!}">{{ $cur[0] }}</a>
  @if ($cur[2])
              <a class="f-btn" href="{!! $cur[2] !!}">{!! __('Delete link') !!}</a>
  @endif
            </li>
@endforeach
          </ol>
        </div>
      </section>
