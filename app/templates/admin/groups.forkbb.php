@extends ('layouts/admin')
      <section id="fork-grlist" class="f-admin">
        <h2>{!! __('User groups') !!}</h2>
        <div class="f-adiv">
          <fieldset>
            <p>{!! __('Edit groups info') !!}</p>
            <ol>
@foreach ($p->groupsList as $cur)
              <li>
                <a href="{{ $cur[1] }}">{{ $cur[0] }}</a>
    @if ($cur[2])
                <a class="f-btn" href="{{ $cur[2] }}" title="{{ __('Delete link') }}">{!! __('Delete link') !!}</a>
    @endif
              </li>
@endforeach
            </ol>
          </fieldset>
        </div>
      </section>
      <section id="fork-grdef" class="f-admin">
        <h2>{!! __('Default group subhead') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formDefault)
    @include ('layouts/form')
@endif
        </div>
      </section>
      <section id="fork-gradd" class="f-admin">
        <h2>{!! __('Add group subhead') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formNew)
    @include ('layouts/form')
@endif
        </div>
      </section>
