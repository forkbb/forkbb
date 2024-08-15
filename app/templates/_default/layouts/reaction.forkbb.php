@isset ($reactions['action'])
              <form class="f-reaction-form" method="post" action="{{ $reactions['action'] }}">
@endisset
                <div class="f-reaction-div">
@foreach ($reactions['visible'] as $key => $cur)
                  <span class="f-reaction-block @if ($key === $post->selectedReaction) f-reaction-selected @endif"><!-- inline -->
                    <button class="f-retype f-retype-btn f-retype-{{ $key }}" type="submit" name="{{ $key }}" value="{{ $key }}" title="{!! $title = __(":{$key}:") !!}" @empty ($cur[1]) disabled @endempty>
                      <small>{!! $title !!}</small>
                    </button>
    @if ($cur[0] > 0)
                    <span class="f-retype-count">{{ $cur[0] }}</span>
    @endif
                  </span><!-- endinline -->
@endforeach
@if ($reactions['hidden'])
                  <input id="id-reaction-{!! (int) $post->id !!}" class="f-reaction-checkbox" type="checkbox">
                  <span class="f-reaction-block f-reaction-toggle"><!-- inline -->
                    <label class="f-retype f-retype-btn f-retype-toggle" for="id-reaction-{!! (int) $post->id !!}"><span class="f-retype-tspan">...</span></label>
                  </span><!-- endinline -->
    @foreach ($reactions['hidden'] as $key => $cur)
                  <span class="f-reaction-block f-reaction-hblock"><!-- inline -->
                    <button class="f-retype f-retype-btn f-retype-{{ $key }}" type="submit" name="{{ $key }}" value="{{ $key }}" title="{!! $title = __(":{$key}:") !!}" @empty ($cur[1]) disabled @endempty>
                      <small>{!! $title !!}</small>
                    </button>
                  </span><!-- endinline -->
    @endforeach
@endif
                </div>
@isset ($reactions['action'])
              </form>
@endisset
