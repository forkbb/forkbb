@extends ('layouts/pm')
      <section id="fork-pm-bl" class="f-pm f-pm-bl-list @empty ($p->blockList) f-pm-bl-empty @endempty">
        <h2>{!! __('Blocked users title') !!}</h2>
@empty ($p->blockList)
    @php $iswev = [FORK_MESS_INFO => ['No blocked users']]; @endphp
    @include ('layouts/iswev')
@else
        <div>
          <fieldset>
            <ol id="fork-pm-bl-ol">
    @foreach ($p->blockList as $user)
              <li class="f-pm-bl-li">
                <a href="{{ $user->link }}">{{ $user->username }}</a>
                <a class="f-btn" href="{{ $user->linkPMUnblock }}" title="{{ __(['Unblock user %s', $user->username]) }}">{!! __('Unblock') !!}</a>
              </li>
    @endforeach
            </ol>
          </fieldset>
        </div>
@endempty
      </section>
