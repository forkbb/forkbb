@extends ('layouts/pm')
      <section id="fork-pm-bl" class="f-pm f-forum @empty ($p->blockList) f-pm-bl-empty @endempty">
        <h2>{!! __('Blocked users title') !!}</h2>
@if (empty($p->blockList) && $iswev = ['i' => ['No blocked users']])
        @include ('layouts/iswev')
@else
        <div>
          <fieldset>
            <ol>
@foreach ($p->blockList as $user)
              <li>
                <a href="{{ $user->link }}">{{ $user->username }}</a>
                <a class="f-btn" href="{{ '?' }}" title="{{ __('Unblock') }}">{!! __('Unblock') !!}</a>
              </li>
@endforeach
            </ol>
          </fieldset>
        </div>
@endif
      </section>
